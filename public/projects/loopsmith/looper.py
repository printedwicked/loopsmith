"""Turn a clip into a seamless web loop.

    python3 looper.py analyze SRC
    python3 looper.py build SRC OUT [--start S --end E] [options]

`analyze` scores candidate loop windows. `build` cuts the window and closes
it with a cross-dissolve arranged so the wrap is frame-continuous:

    M = L - X
    O[k] = lerp(S[M+k], S[k], k/X)  for k < X
    O[k] = S[k]                     for k >= X

O[M-1] is S[M-1] and O[0] blends in S[M] — adjacent frames in the source —
so playback wraps with no jump; the dissolve only masks the tail fading out.

Options exist because generated clips arrive with predictable defects:
--crush lifts a not-quite-black backdrop to true 0 (required for
mix-blend-mode: screen), --crop-bottom removes a lit studio floor, --grade
warms neutral sand toward brass.
"""

import argparse
import subprocess
import sys

import numpy as np
from PIL import Image

FPS = 24


def extract(src, outdir, scale=None):
    subprocess.run(["rm", "-rf", outdir], check=True)  # noqa: S603, S607  fixed rm/mkdir/ffmpeg argv, no shell
    subprocess.run(["mkdir", "-p", outdir], check=True)  # noqa: S603, S607  fixed rm/mkdir/ffmpeg argv, no shell
    cmd = ["ffmpeg", "-v", "error", "-i", src]
    if scale:
        cmd += ["-vf", f"scale={scale}"]
    cmd += [f"{outdir}/%04d.png", "-y"]
    subprocess.run(cmd, check=True)  # noqa: S603  fixed rm/mkdir/ffmpeg argv, no shell
    return sorted(__import__("glob").glob(f"{outdir}/*.png"))


def load_gray(paths):
    return np.stack([
        np.asarray(Image.open(p).convert("L"), dtype=np.float32) / 255 for p in paths
    ])


def analyze(src, min_seconds=3.0, top=6):
    paths = extract(src, "_an", scale="160:-2")
    a = load_gray(paths)
    n = len(a)
    consec = float(np.median([np.abs(a[i + 1] - a[i]).mean() for i in range(n - 1)]))
    wrap = float(np.abs(a[-1] - a[0]).mean())

    print(f"frames={n} ({n / FPS:.2f}s)  median frame delta={consec:.4f}")
    print(f"whole-clip wrap delta={wrap:.4f} ({wrap / consec:.2f}x) "
          f"{'- loops as-is' if wrap / consec < 1.5 else '- does NOT loop as-is'}")

    coverage = [(x > 0.30).mean() for x in a]
    drift = (coverage[-1] - coverage[0]) / max(coverage[0], 1e-6)
    print(f"coverage drift start->end: {drift:+.1%} "
          f"{'(steady)' if abs(drift) < 0.08 else '(accumulating - limits loop options)'}")

    minlen = int(min_seconds * FPS)
    cands = [(float(np.abs(a[j] - a[i]).mean()), i, j) for i in range(0, n - minlen) for j in range(i + minlen, n)]
    cands.sort()

    print(f"\nbest windows >= {min_seconds}s:")
    seen = []
    for d, i, j in cands:
        if any(abs(i - x) < 10 and abs(j - y) < 10 for x, y in seen):
            continue
        seen.append((i, j))
        print(f"  {i / FPS:5.2f}s -> {j / FPS:5.2f}s  len={(j - i) / FPS:4.2f}s  "
              f"delta={d:.4f} ({d / consec:.2f}x)")
        if len(seen) >= top:
            break
    return consec


def crush_blacks(rgb, black_point):
    return np.clip((rgb - black_point) / (1.0 - black_point), 0.0, 1.0)


def brass_grade(rgb):
    """Warm neutral sand without gilding the bone highlights."""
    lum = (rgb * np.array([0.2126, 0.7152, 0.0722])).sum(-1, keepdims=True)
    warm = rgb * np.array([1.12, 0.90, 0.58])
    w = np.clip(1.0 - lum * 0.75, 0.0, 1.0)
    return np.clip(rgb * (1 - w) + warm * w, 0, 1)


def build(src, out, start, end, fade, crush, crop_bottom, grade, scale, crf):
    paths = extract(src, "_bf", scale=scale)
    i0, i1 = round(start * FPS), round(end * FPS)
    seg = [np.asarray(Image.open(p).convert("RGB")) for p in paths[i0:i1 + 1]]
    L, X = len(seg), fade
    if X >= L:
        sys.exit("crossfade longer than the segment")
    M = L - X
    print(f"segment {start:.2f}s->{end:.2f}s = {L} frames; "
          f"loop = {M} frames ({M / FPS:.2f}s) after a {X}-frame dissolve")

    frames = []
    for k in range(M):
        if k < X:
            t = k / X
            f = (1 - t) * seg[M + k].astype(np.float32) + t * seg[k].astype(np.float32)
        else:
            f = seg[k].astype(np.float32)
        f /= 255.0
        if crush > 0:
            f = crush_blacks(f, crush)
        if grade:
            f = brass_grade(f)
        if crop_bottom:
            f = f[: f.shape[0] - crop_bottom]
        if f.shape[0] % 2:
            f = f[:-1]
        if f.shape[1] % 2:
            f = f[:, :-1]
        frames.append((np.clip(f, 0, 1) * 255).astype(np.uint8))

    g = np.stack([np.asarray(Image.fromarray(f).convert("L"), dtype=np.float32) / 255
                  for f in frames])
    wrap = np.abs(g[0] - g[-1]).mean()
    consec = np.median([np.abs(g[i + 1] - g[i]).mean() for i in range(len(g) - 1)])
    print(f"loop wrap delta={wrap:.4f}  median frame delta={consec:.4f}  "
          f"ratio={wrap / consec:.2f}x {'(seamless)' if wrap / consec <= 1.3 else '(visible)'}")

    subprocess.run(["rm", "-rf", "_of"], check=True)  # noqa: S607  fixed rm/mkdir/ffmpeg argv, no shell
    subprocess.run(["mkdir", "-p", "_of"], check=True)  # noqa: S607  fixed rm/mkdir/ffmpeg argv, no shell
    for i, f in enumerate(frames):
        Image.fromarray(f).save(f"_of/{i:04d}.png")
    subprocess.run([  # noqa: S603, S607  fixed rm/mkdir/ffmpeg argv, no shell
        "ffmpeg", "-v", "error", "-framerate", str(FPS), "-i", "_of/%04d.png",
        "-c:v", "libx264", "-preset", "slow", "-crf", str(crf), "-pix_fmt", "yuv420p",
        # Dark, grainy content bands badly; adaptive quant protects the blacks.
        "-x264-params", "aq-mode=3:aq-strength=1.1:deblock=-1,-1",
        "-movflags", "+faststart", "-an", out, "-y",
    ], check=True)
    print(f"wrote {out} ({frames[0].shape[1]}x{frames[0].shape[0]})")


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("mode", choices=["analyze", "build"])
    ap.add_argument("src")
    ap.add_argument("out", nargs="?")
    ap.add_argument("--start", type=float, default=0)
    ap.add_argument("--end", type=float, default=0)
    ap.add_argument("--fade", type=int, default=14)
    ap.add_argument("--crush", type=float, default=0.0)
    ap.add_argument("--crop-bottom", type=int, default=0)
    ap.add_argument("--grade", action="store_true")
    ap.add_argument("--scale", default=None)
    ap.add_argument("--crf", type=int, default=21)
    ap.add_argument("--min-seconds", type=float, default=3.0)
    a = ap.parse_args()

    if a.mode == "analyze":
        analyze(a.src, a.min_seconds)
    else:
        build(a.src, a.out, a.start, a.end, a.fade, a.crush,
              a.crop_bottom, a.grade, a.scale, a.crf)

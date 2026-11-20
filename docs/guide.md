# Loopsmith

A browser tool for turning a clip that doesn't loop into one that does —
video in the top half, **audio in the bottom half**.

Generated video almost never loops on its own — the motion keeps building,
so the last frame doesn't match the first and you get a visible jump every
cycle. Loopsmith compares every frame against every other, finds the pair
that matches closest, and closes the gap with a cross-dissolve.

## Running it

The page is `public/projects/loopsmith/index.php`; serve the `public/` folder with any PHP host
(`php -S 127.0.0.1:8080 -t public` for a local run) and open `/projects/loopsmith/`. No build step,
no dependencies, no server code beyond the small PHP includes in `public/includes/`.

The only external request the page makes is to Google Fonts. It works
without them — the fallback stack is real — so it is safe behind a strict
CSP or offline.

## The samples

The page is built to open with a file already loaded, analysed and looping:
a tool that opens as an empty drop zone tells a visitor nothing about whether
it is worth finding a file for. This repository ships no sample media, so both
halves open empty. Put a short clip and a short mp3 beside `index.php` and set
`DEMO` and `DEMO_AUDIO` (near the bottom of the script) to their file names;
the notes below describe the clip the page was tuned on.

## The sample clip

When a sample is present (`DEMO` set to a clip beside `index.php`) the page opens with it
already loaded, analysed and looping. The clip the page was tuned on was a 2.5 s generated
clip, 480 px wide, 38 KB; it is not in this repository.

Two decisions worth keeping when you choose one:

- **VP9 WebM, not H.264 MP4.** This page's own warning is that Chromium
  builds without proprietary codecs cannot decode MP4. A sample clip that
  fails to load in some browsers would demonstrate the opposite of what it
  is there for.
- **Deferred to `requestIdleCallback`.** Analysis is dozens of seeks and
  must not compete with first paint. A generation counter in the script
  means a file you drop while the sample is still analysing wins — without
  it, the sample's results land on top of yours a second later.

Pick a clip that *doesn't* loop as-is but has a plateau in it somewhere.
The clip it was tuned on was a misty pine forest — 121 frames, 31 KB — and it was a
good demo precisely because the before and after are both dramatic: 39.36×
as-is, 0.01× once the dissolve lands.

What loops badly, learned by measuring rather than guessing: **breaking
waves and other chaotic motion**. A beach clip generated for this scored
9.15× as-is and closed to 0.98× — fine, but far less of a story. Slow drift
over a mostly-steady frame closes to a hundredth of that.

## Using it — video

**Set the frame rate first** (bottom right, default 24) so it matches the
source, then drop the clip in. Everything else follows from that number.

The three tiles across the top answer, in order: how much an ordinary frame
changes, whether the clip already loops, and whether the motion is
accumulating. That last one is the one that matters most — if the scene
keeps building, only a plateau in the middle will ever loop, and no
dissolve can fix a clip that grows from start to finish.

The **loop map** compares every frame against every other. Bright means
similar, so each bright point away from the diagonal is a pair that could
loop. The dim band around the diagonal is too short to be useful. Click a
bright point to select that window, or pick from the ranked candidates.

The preview renders **by itself** as soon as analysis finishes — watching
the seam is the whole point of the analysis, so making it a button press
was a step with no decision in it. The button stays for re-rendering after
you change something.

## Reading the score — two numbers, not one

**This is the thing most likely to be got wrong, and it was got wrong here
once already.** There are two ratios on the page and they measure different
things:

- The **× in the candidate list** is the *raw* seam, before any dissolve.
  It ranks windows against each other. On real footage it runs from 7× to
  40× even when the finished loop is flawless. It is a statement of the
  problem, not of the result.
- The **seam under the preview** is the finished loop, scored the same way
  after the dissolve is applied. That is the number that says whether it
  worked.

They are routinely an order of magnitude apart. The clip the page was tuned on scored
**39.36× as-is**, its best window was **16.53× raw**, and the finished loop
was **0.01×** — invisible. Judging that clip by the candidate list would
have thrown away perfect material, which is exactly what happened while
this was being built.

The candidate list is therefore coloured *relative to the best window in
it*, not against the absolute scale below. The absolute scale applies only
to the post-dissolve number:

| ratio | meaning |
|---|---|
| ≤ 1.0 | the seam changes *less* than an ordinary frame — invisible |
| 1.0–1.3 | effectively seamless |
| 1.3–2.0 | a visible pulse each cycle |
| > 2.0 | a hard cut |

A longer dissolve will pull a bad ratio down, but it softens that fraction
of the loop — 30 dissolve frames in a 90-frame loop means a third of it is
cross-blended. Prefer a better window over a longer dissolve.

## Controls

- **Black point** — lifts a not-quite-black background to true 0. Needed if
  you plan to composite with `mix-blend-mode: screen`, which only treats
  actual 0 as transparent. "Looks black" usually isn't.
- **Warm grade** — pushes neutral toward brass, weighted so highlights are
  spared.
- **Output width** — match how large the video actually renders on the
  page, not the source resolution. A clip displayed 700px wide gains
  nothing from a 1080px encode.
- **Bitrate** — dark, grainy footage bands below about 4 Mbps.

## Limits

Export writes **VP9/WebM** (or MP4 where the browser supports recording
it) at a fixed bitrate. For a final MP4 with proper control over banding in
the blacks, the page prints the equivalent `looper.py` command — that path
uses x264 with adaptive quantisation tuned for dark content.

**H.264 MP4 input needs Chrome or Edge.** Chromium builds without
proprietary codecs cannot decode it; WebM works in any browser. The page
says so rather than failing silently.

Encoding happens in real time by design: the recorder timestamps frames by
wall clock, so they have to be fed at playback speed or the exported file
comes out several times too long.

Nothing is uploaded. Decoding and encoding both happen in the tab.


## The sample sound, and the switch

Keep a sample sound small: 48 kbps is transparent for material with no
content above 1 kHz at all. Check that before encoding one: a wideband file
needs a great deal more.

**Nothing plays until the switch is pressed.** Not muted-and-running —
genuinely not started, with no AudioContext live. An earlier version
started the loop silently at page load so unmuting would feel instant, and
it was worse mechanics for no real gain: a page quietly running an audio
graph nobody asked for, where "muted" and "not playing" look identical to a
visitor and behave differently to debug.

The switch sits **in the flow of the page**, full width, at 0.9rem. Every
other page on the site puts its sound toggle in the bottom-right corner at
0.6rem, where it is findable but not offered. Here the sound is the point
of the panel it sits in.

The gain ramps over 120 ms rather than switching, and on stop the source is
released only after the ramp finishes — starting or stopping a bass-heavy
loop on a hard edge is a thump.

## Using it — audio

Same idea, different medium, and a separate code path rather than the video
tool with a flag through it.

**The comparison is spectral, not sample-by-sample.** Two moments of a hiss
can be completely uncorrelated as waveforms and be the same sound. Matching
waveforms would score them as maximally different and find nothing; the
feature per analysis window is a 24-band spectrum in dB, log-spaced from
40 Hz, which is much closer to what the ear does. Everything downstream —
the map, the ranking, the seam ratio — is the same arithmetic the video
half runs on downsampled luma.

The FFT is written out in the page. That is deliberate: this file has no
dependencies, which means it can be opened from disk with no network at
all.

### The seam number

The audio half reports something the video half cannot: **the single sample
step that actually plays at the loop point**, compared against the
distribution of every ordinary adjacent step in the file.

| join is larger than | verdict |
|---|---|
| under 90% of ordinary steps | an ordinary step — inaudible |
| 90–99% | larger than most — listen for it |
| over 99% | you will hear a click |

The obvious alternative — comparing the largest step in a window around the
join against the largest in a window elsewhere — throws false positives on
anything with sparse loud transients: whichever window happens to contain a
bigger one wins, and the answer says nothing about the join. That mistake
was made once already on a real loop.

### Controls

- **Crossfade** — equal power by default. Linear ramps between two
  uncorrelated signals dip about 3 dB at the midpoint, which on noise-like
  material is plainly audible as a breath. Past about 2s the fade starts
  eating the loop.
- **Snap to zero crossing** — only does anything at 0 ms crossfade, where
  the join is a hard cut. With any crossfade the join is continuous by
  construction.
- **Normalise** — off by default. A level change is still a change, and
  the gain belongs in whatever plays the file, not baked into it.
- **Analysis hop** — finer finds tighter windows and costs more time. The
  map is n², so the page widens the hop by itself past 420 windows and
  says so rather than silently taking four times as long.

### Cutting the loop

The same rule as video: `out[k]` for `k ≥ X` is the source, and for `k < X`
it is the head crossfaded with the tail. The last sample of the loop and
the first are *adjacent source samples*, so playback wraps with no
discontinuity — the crossfade masks the tail fading out, it does not create
the join.

Preview plays through a looping `AudioBufferSourceNode`, so the browser
wraps the buffer itself and what you hear is exactly what the file will do.

### Export

WAV, 16 or 24-bit, written by hand because no browser will encode anything
else. That is not worth apologising for: WAV is right as the output of a
tool whose whole purpose is a sample-accurate join, and re-encoding is a
decision to make afterwards with a real encoder.

The page also prints the equivalent numpy/soundfile snippet — the same
arithmetic, for batching, and because a loop you can regenerate from a
script is a loop you still have next year.

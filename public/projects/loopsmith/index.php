<!doctype html>
<html lang="en">
<head>
<!-- PHP includes, not SSI. include takes a filesystem path, hence DOCUMENT_ROOT: a bare
     "/includes/head.php" would be looked up from the server's root directory rather than
     the site's, and would silently produce nothing. -->

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#08080b">
<title>Loopsmith</title>
<meta name="description" content="Find where a clip loops, close the seam, and export it - entirely in the browser.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%2308080b'/%3E%3Ctext y='24' x='16' text-anchor='middle' font-size='20'%3E%F0%9F%94%81%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;900&family=Archivo:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php'; ?>
<?php pw_light_css('/projects/loopsmith/loopsmith.light.css'); ?>

<style>
  :root {
    /* Committed dark instrument. This tool exists to judge black levels and
       dark footage — a light ground would distort exactly the call it is
       built to help you make. Every colour is painted explicitly so the
       page holds on any host ground. */
    --void: #08080b;
    --panel: #101015;
    --panel-2: #16161d;
    --rule: #24242e;
    --rule-hot: #3a3a49;
    --bone: #ece6d8;
    --bone-dim: #8b8477;
    --bone-faint: #5c574e;
    --brass: #2fc9b4;
    --brass-hot: #8ef0e2;
    --brass-deep: #10564f;
    /* Semantic state, deliberately outside the brass accent family. The
       accent is verdigris now, so --ok sits only 22 degrees away in hue;
       it stays distinguishable by being warmer and duller, but if the two
       ever read as one colour it is --ok that should move, not the brand. */
    --ok: #5fb389;
    --warn: #e0862f;
    --bad: #cf4f3e;

    --f-display: "Cinzel", "Times New Roman", serif;
    --f-ui: "Archivo", system-ui, -apple-system, sans-serif;
    --f-data: "JetBrains Mono", ui-monospace, "SF Mono", monospace;

    --s1: 0.35rem; --s2: 0.7rem; --s3: 1.1rem; --s4: 1.7rem; --s5: 2.6rem;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    background: var(--void);
    color: var(--bone);
    font-family: var(--f-ui);
    font-size: 15px;
    line-height: 1.55;
    -webkit-font-smoothing: antialiased;
  }

  ::selection { background: var(--brass); color: var(--void); }

  .wrap { max-width: 1180px; margin: 0 auto; padding: var(--s4) var(--s3) var(--s5); }

  /* ---------- header ---------- */
  header {
    display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
    gap: var(--s3);
    padding-bottom: var(--s3);
    border-bottom: 2px solid var(--rule);
    margin-bottom: var(--s4);
  }
  .mark {
    font-family: var(--f-display); font-weight: 900;
    font-size: clamp(1.5rem, 3.4vw, 2.1rem);
    letter-spacing: 0.1em; margin: 0; line-height: 1;
  }
  .mark span { color: var(--brass); }
  .tagline {
    font-family: var(--f-data); font-size: 0.72rem; letter-spacing: 0.16em;
    text-transform: uppercase; color: var(--bone-dim); margin: var(--s2) 0 0;
  }

  /* ---------- controls ---------- */
  button, .filebtn {
    font-family: var(--f-ui); font-weight: 600; font-size: 0.82rem;
    letter-spacing: 0.05em; text-transform: uppercase;
    padding: 0.6rem 1rem; border: 2px solid var(--bone);
    background: transparent; color: var(--bone); cursor: pointer;
    transition: background .14s, color .14s, border-color .14s;
  }
  button:hover:not(:disabled), .filebtn:hover {
    background: var(--brass); border-color: var(--brass); color: var(--void);
  }
  button:disabled { opacity: .35; cursor: not-allowed; }
  button.primary { background: var(--brass); border-color: var(--brass); color: var(--void); }
  button.primary:hover:not(:disabled) { background: var(--brass-hot); border-color: var(--brass-hot); }
  :focus-visible { outline: 2px solid var(--brass-hot); outline-offset: 2px; }

  input[type="file"] { display: none; }

  /* ---------- drop zone ---------- */
  .drop {
    border: 2px dashed var(--rule-hot); padding: var(--s5) var(--s3);
    text-align: center; transition: border-color .15s, background .15s;
  }
  .drop.over { border-color: var(--brass); background: #0d0d12; }
  .drop h2 { font-family: var(--f-ui); font-weight: 700; font-size: 1.15rem; margin: 0 0 var(--s2); }
  .drop p { color: var(--bone-dim); max-width: 56ch; margin: 0 auto var(--s3); font-size: 0.92rem; }

  .hint { font-family: var(--f-data); font-size: 0.72rem; color: var(--bone-faint); letter-spacing: .04em; }

  /* ---------- layout ---------- */
  .hidden { display: none !important; }
  .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: var(--s2); margin-bottom: var(--s4); }
  .tile { background: var(--panel); border: 1px solid var(--rule); padding: var(--s3); }
  .tile .lab {
    font-family: var(--f-data); font-size: 0.66rem; letter-spacing: .16em;
    text-transform: uppercase; color: var(--bone-dim); display: block; margin-bottom: var(--s1);
  }
  .tile .val {
    font-family: var(--f-data); font-size: 1.5rem; font-weight: 600;
    font-variant-numeric: tabular-nums; line-height: 1.1;
  }
  .tile .sub { font-size: 0.76rem; color: var(--bone-dim); margin-top: var(--s1); }

  .pill {
    display: inline-block; font-family: var(--f-data); font-size: 0.65rem;
    letter-spacing: .1em; text-transform: uppercase; padding: 0.18rem 0.5rem;
    border: 1px solid currentColor; margin-top: var(--s2);
  }
  .pill.ok { color: var(--ok); } .pill.warn { color: var(--warn); } .pill.bad { color: var(--bad); }

  .cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: var(--s4); align-items: start; }
  @media (max-width: 860px) { .cols { grid-template-columns: minmax(0, 1fr); } }

  h3.sec {
    font-family: var(--f-data); font-size: 0.7rem; letter-spacing: .18em;
    text-transform: uppercase; color: var(--brass); margin: 0 0 var(--s2);
    padding-bottom: var(--s1); border-bottom: 1px solid var(--rule);
  }
  .note { font-size: 0.8rem; color: var(--bone-dim); margin: var(--s2) 0 0; }

  /* ---------- matrix ---------- */
  .matrixbox { position: relative; background: var(--panel); border: 1px solid var(--rule); padding: var(--s2); }
  #matrix { width: 100%; height: auto; display: block; image-rendering: pixelated; cursor: crosshair; }
  .axis {
    display: flex; justify-content: space-between;
    font-family: var(--f-data); font-size: 0.65rem; color: var(--bone-faint);
    margin-top: var(--s1); font-variant-numeric: tabular-nums;
  }

  /* ---------- candidates ---------- */
  .cands { border: 1px solid var(--rule); background: var(--panel); max-height: 260px; overflow-y: auto; }
  .cand {
    display: grid; grid-template-columns: 1fr auto auto; gap: var(--s2);
    align-items: center; width: 100%; text-align: left;
    padding: 0.55rem var(--s2); border: 0; border-bottom: 1px solid var(--rule);
    background: transparent; text-transform: none; letter-spacing: 0;
    font-family: var(--f-data); font-size: 0.78rem; font-variant-numeric: tabular-nums;
    color: var(--bone);
  }
  .cand:last-child { border-bottom: 0; }
  .cand:hover { background: var(--panel-2); color: var(--bone); }
  .cand[aria-selected="true"] { background: var(--brass-deep); color: var(--bone); }
  .cand .len { color: var(--bone-dim); }
  .cand .ratio { font-weight: 600; }

  /* ---------- preview ---------- */
  .preview { background: #000; border: 1px solid var(--rule); display: grid; place-items: center; min-height: 220px; position: relative; }
  #pcanvas { max-width: 100%; max-height: 420px; display: block; }
  .previewnote {
    position: absolute; left: var(--s2); bottom: var(--s2);
    font-family: var(--f-data); font-size: 0.66rem; letter-spacing: .1em;
    text-transform: uppercase; color: var(--brass); background: rgba(8,8,11,.8); padding: .2rem .45rem;
  }

  /* ---------- fields ---------- */
  .fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--s2); }
  .field label {
    display: block; font-family: var(--f-data); font-size: 0.66rem;
    letter-spacing: .14em; text-transform: uppercase; color: var(--bone-dim); margin-bottom: var(--s1);
  }
  .field input, .field select {
    width: 100%; background: var(--panel-2); border: 1px solid var(--rule);
    color: var(--bone); font-family: var(--f-data); font-size: 0.85rem;
    padding: 0.45rem 0.55rem; font-variant-numeric: tabular-nums;
  }
  .field .desc { font-size: 0.72rem; color: var(--bone-faint); margin-top: var(--s1); }

  .actions { display: flex; flex-wrap: wrap; gap: var(--s2); margin-top: var(--s3); align-items: center; }

  /* ---------- progress ---------- */
  .bar { height: 3px; background: var(--rule); overflow: hidden; margin-top: var(--s2); }
  .bar i { display: block; height: 100%; width: 0; background: var(--brass); transition: width .15s linear; }
  .status { font-family: var(--f-data); font-size: 0.74rem; color: var(--bone-dim); margin-top: var(--s1); }
  .status.err { color: var(--bad); }
  .status.good { color: var(--ok); }

  /* ---------- cli ---------- */
  pre.cli {
    background: var(--panel); border: 1px solid var(--rule); border-left: 3px solid var(--brass);
    padding: var(--s3); overflow-x: auto; font-family: var(--f-data);
    font-size: 0.78rem; color: var(--bone); margin: 0;
  }

  footer { margin-top: var(--s5); padding-top: var(--s3); border-top: 1px solid var(--rule); color: var(--bone-faint); font-size: 0.8rem; }


  /* ---------- the split between the two halves ---------- */

  /* Loopsmith did one thing for its whole life and now does two. The rule
     is doing real work: without it the audio drop zone reads as another
     control belonging to the video tool above it, because everything on
     this page shares one visual language on purpose. */
  hr.split {
    border: 0;
    border-top: 2px solid var(--rule);
    margin: var(--s5) 0 var(--s4);
  }

  .subhead {
    display: flex; flex-wrap: wrap; align-items: flex-end;
    justify-content: space-between; gap: var(--s3);
    margin-bottom: var(--s4);
  }
  .subhead h2 {
    font-family: var(--f-display); font-weight: 900;
    font-size: clamp(1.2rem, 2.6vw, 1.6rem);
    letter-spacing: 0.1em; margin: 0; line-height: 1;
  }
  .subhead h2 span { color: var(--brass); }

  /* ---------- the sound switch ----------------------------------------

     Every other page on the site puts its sound toggle in the bottom
     right corner at 0.6rem, where it is findable but not offered. Here
     the sound IS the point of the panel it sits in, so it goes into the
     flow of the page at a size that admits that. */
  .soundbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: var(--s3);
    border: 1px solid var(--rule); border-left: 3px solid var(--brass);
    padding: var(--s3); margin-bottom: var(--s4);
  }
  .soundbar p { margin: 0; font-size: 0.86rem; color: var(--bone-dim); flex: 1 1 18ch; }
  .soundbar p b { color: var(--bone); font-weight: 600; }

  .soundtoggle {
    display: inline-flex; align-items: center; gap: 0.6rem;
    font-size: 0.9rem; padding: 0.75rem 1.25rem;
    border-width: 2px; flex: 0 0 auto;
  }
  .soundtoggle[aria-pressed="true"] {
    background: var(--brass); border-color: var(--brass); color: var(--void);
  }

  /* Three bars: flat while muted, moving while audible. The state is
     legible across the room without reading the word, which is the point
     of having a glyph at all. */
  .bars { display: flex; align-items: flex-end; gap: 2px; height: 0.9rem; }
  .bars i { display: block; width: 3px; background: currentColor; transition: height .2s ease; }
  .bars i:nth-child(1) { height: 4px; }
  .bars i:nth-child(2) { height: 10px; }
  .bars i:nth-child(3) { height: 6px; }
  .soundtoggle[aria-pressed="true"] .bars i { animation: barjig 1.15s ease-in-out infinite; }
  .soundtoggle[aria-pressed="true"] .bars i:nth-child(2) { animation-delay: -0.38s; }
  .soundtoggle[aria-pressed="true"] .bars i:nth-child(3) { animation-delay: -0.72s; }
  @keyframes barjig { 0%, 100% { height: 3px; } 50% { height: 0.9rem; } }
  @media (prefers-reduced-motion: reduce) {
    .soundtoggle[aria-pressed="true"] .bars i { animation: none; height: 0.6rem; }
  }

  .soundbar { background: rgba(16, 16, 21, 0.72);
    -webkit-backdrop-filter: blur(14px) saturate(1.1); backdrop-filter: blur(14px) saturate(1.1); }
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
    .soundbar { background: rgba(10, 10, 13, 0.94); }
  }

  /* ---------- waveform ---------- */

  /* The audio half's answer to the video preview. A pour or a hiss looks
     like a featureless slab at this scale, which is exactly why the
     crossfade region is drawn as a band rather than left to be inferred:
     the eye needs to be told where the seam is, because the waveform will
     not show it. */
  .wavebox {
    background: #000; border: 1px solid var(--rule);
    position: relative; min-height: 160px;
    display: grid; place-items: stretch;
  }
  #awave { width: 100%; height: 160px; display: block; }
  .wavenote {
    position: absolute; left: var(--s2); bottom: var(--s2);
    font-family: var(--f-data); font-size: 0.66rem; letter-spacing: .1em;
    text-transform: uppercase; color: var(--brass);
    background: rgba(8,8,11,.8); padding: .2rem .45rem;
  }

  /* The seam verdict sits under the waveform and is the one number worth
     reading twice — see the comment on seamReport() in the script. */
  .seam {
    font-family: var(--f-data); font-size: 0.76rem;
    border: 1px solid var(--rule); border-left: 3px solid var(--rule-hot);
    background: var(--panel); padding: var(--s2);
    margin-top: var(--s2); color: var(--bone-dim);
  }
  .seam b { color: var(--bone); font-weight: 600; }
  .seam.ok { border-left-color: var(--ok); }
  .seam.warn { border-left-color: var(--warn); }
  .seam.bad { border-left-color: var(--bad); }

  .wavebox, .seam {
    background: rgba(16, 16, 21, 0.72);
    -webkit-backdrop-filter: blur(14px) saturate(1.1);
    backdrop-filter: blur(14px) saturate(1.1);
  }
  .wavebox { background: rgba(0, 0, 0, 0.72); }
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
    .wavebox, .seam { background: rgba(10, 10, 13, 0.94); }
  }

  @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }

  /* ---- Sitting on the shared stage --------------------------------------
     Loopsmith drops onto the sand background with little friction because
     the palettes already agree. What changes is opacity: every surface
     that was a flat slab becomes glass, so the dust and sand stay visible
     behind the tool rather than being cancelled out by it. */

  body { background: transparent; }

  .wrap { position: relative; z-index: 1; }

  .tile,
  .matrixbox,
  .cands,
  .preview,
  pre.cli,
  .field input,
  .field select {
    background: rgba(16, 16, 21, 0.72);
    -webkit-backdrop-filter: blur(14px) saturate(1.1);
    backdrop-filter: blur(14px) saturate(1.1);
  }

  /* The drop zone stays quiet — it is a target, not a panel. */
  .drop {
    background: rgba(8, 8, 11, 0.45);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
  }

  .drop.over { background: rgba(13, 13, 18, 0.8); }
  .cand:hover { background: rgba(22, 22, 29, 0.85); }

  /* Without backdrop-filter a translucent panel over this background is
     unreadable, so fall back to something nearly opaque. */
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
    .tile, .matrixbox, .cands, .preview, pre.cli, .drop {
      background: rgba(10, 10, 13, 0.94);
    }
  }
</style>
</head>
<body class="pw-on-stage pw-accent-verdigris">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/stage.html'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/nav.php'; ?>

<div class="wrap">
  <header>
    <div>
      <h1 class="mark"><?= cms_block('loopsmith-mark-loopsmith', 'LOOP<span>SMITH</span>') ?></h1>
      <p class="tagline"><?= cms_block('loopsmith-tagline-find-the-seam-close', 'Find the seam · close it · ship it') ?></p>
    </div>
    <div>
      <label class="filebtn" for="file" tabindex="0" role="button">Choose video</label>
      <input type="file" id="file" accept="video/*">
    </div>
  </header>

  <section id="intro" class="drop">
    <h2><?= cms_block('loopsmith-h2-drop-a-clip-to', 'Drop a clip to find where it loops') ?></h2>
    <p><?= cms_block('loopsmith-p-generated-video-almost-nev', '
      Generated video almost never loops on its own — the motion keeps building, so the
      last frame doesn\'t match the first and you get a visible jump every cycle.
      This compares every frame against every other, finds the pair that matches
      closest, and closes the gap with a cross-dissolve.
    ') ?></p>
    <p class="hint">
      <span id="demoflag">Drop a clip to begin.</span>
      Nothing is uploaded. Decoding and encoding happen in this tab.
    </p>
  </section>

  <section id="work">
    <div class="tiles" id="tiles"></div>

    <div class="cols">
      <div>
        <h3 class="sec"><?= cms_block('loopsmith-sec-loop-map', 'Loop map') ?></h3>
        <div class="matrixbox">
          <canvas id="matrix" width="10" height="10"></canvas>
          <div class="axis"><span>0s</span><span id="axmid">—</span><span id="axend">—</span></div>
        </div>
        <p class="note"><?= cms_block('loopsmith-note-every-frame-compared-again', '
          Every frame compared against every other. Bright means similar, so each
          bright point away from the diagonal is a pair that could loop.
          The dim band is too short to be useful. Click a bright point to select it.
        ') ?></p>

        <h3 class="sec" style="margin-top:var(--s4)"><?= cms_block('loopsmith-sec-best-candidates', 'Best candidates') ?></h3>
        <div class="cands" id="cands"></div>
        <p class="note"><?= cms_block('loopsmith-note-the-is-the-raw', '
          The × is the <em>raw</em> seam, before any dissolve — it ranks the windows
          against each other, and is routinely tens of × even on footage that closes
          perfectly. The number that says whether a loop worked is under the preview.
        ') ?></p>
      </div>

      <div>
        <h3 class="sec"><?= cms_block('loopsmith-sec-preview', 'Preview') ?></h3>
        <div class="preview">
          <canvas id="pcanvas" width="16" height="9"></canvas>
          <span class="previewnote" id="pnote">No loop selected</span>
        </div>
        <div class="seam" id="vseam">Render a preview to score the seam.</div>
        <p class="note" id="prevdesc">
          Plays the finished loop — dissolve, black point and grade applied — so you
          can watch the seam before committing to an export.
        </p>

        <h3 class="sec" style="margin-top:var(--s4)"><?= cms_block('loopsmith-sec-build', 'Build') ?></h3>
        <div class="fields">
          <div class="field">
            <label for="fStart">Start (s)</label>
            <input type="number" id="fStart" step="0.01" min="0" value="0">
          </div>
          <div class="field">
            <label for="fEnd">End (s)</label>
            <input type="number" id="fEnd" step="0.01" min="0" value="0">
          </div>
          <div class="field">
            <label for="fFade">Dissolve (frames)</label>
            <input type="number" id="fFade" step="1" min="0" value="16">
            <p class="desc"><?= cms_block('loopsmith-desc-longer-hides-a-worse', 'Longer hides a worse seam, but softens more of the loop.') ?></p>
          </div>
          <div class="field">
            <label for="fCrush">Black point</label>
            <input type="number" id="fCrush" step="0.005" min="0" max="0.3" value="0.012">
            <p class="desc"><?= cms_block('loopsmith-desc-lifts-a-not-quite', 'Lifts a not-quite-black background to true 0.') ?></p>
          </div>
          <div class="field">
            <label for="fGrade">Warm grade</label>
            <select id="fGrade"><option value="0">Off</option><option value="1">On</option></select>
            <p class="desc"><?= cms_block('loopsmith-desc-pushes-neutral-toward-bras', 'Pushes neutral toward brass, sparing highlights.') ?></p>
          </div>
          <div class="field">
            <label for="fWidth">Output width</label>
            <input type="number" id="fWidth" step="2" min="120" value="720">
            <p class="desc"><?= cms_block('loopsmith-desc-match-how-large-it', 'Match how large it actually renders, not the source.') ?></p>
          </div>
          <div class="field">
            <label for="fRate">Bitrate (Mbps)</label>
            <input type="number" id="fRate" step="0.5" min="0.5" value="6">
            <p class="desc"><?= cms_block('loopsmith-desc-dark-grainy-footage-bands', 'Dark, grainy footage bands below ~4.') ?></p>
          </div>
          <div class="field">
            <label for="fFps">Frame rate</label>
            <input type="number" id="fFps" step="1" min="1" max="60" value="24">
            <p class="desc"><?= cms_block('loopsmith-desc-set-before-analysing-must', 'Set before analysing; must match the source.') ?></p>
          </div>
        </div>

        <div class="actions">
          <button id="btnPreview">Render preview</button>
          <button id="btnExport" class="primary">Export loop</button>
        </div>
        <div class="bar"><i id=bar></i></div>
        <p class="status" id="status">Ready.</p>
      </div>
    </div>

    <h3 class="sec" style="margin-top:var(--s5)"><?= cms_block('loopsmith-sec-same-build-from-the', 'Same build, from the command line') ?></h3>
    <pre class="cli" id="cli">Select a loop window to generate the command.</pre>
    <p class="note"><?= cms_block('loopsmith-note-browser-export-writes-vp9', '
      Browser export writes VP9/WebM at a fixed bitrate. For a final MP4 — better
      control of banding in the blacks — run the same window through
      <span class="hint">looper.py</span> with x264.
    ') ?></p>

    <footer>
      Loop quality is scored as the wrap
      difference divided by the median frame-to-frame difference: at or under 1.0 the
      seam changes less than an ordinary frame and is invisible.
    </footer>
  </section>

  <hr class="split">

  <div class="subhead">
    <div>
      <h2><?= cms_block('loopsmith-h2-loopsmith-audio', 'LOOP<span>SMITH</span> · AUDIO') ?></h2>
      <p class="tagline"><?= cms_block('loopsmith-tagline-same-idea-different-medium', 'Same idea, different medium') ?></p>
    </div>
    <div>
      <label class="filebtn" for="afile" tabindex="0" role="button">Choose audio</label>
      <input type="file" id="afile" accept="audio/*">
    </div>
  </div>

  <div class="soundbar">
    <button class="soundtoggle" id="aMute" type="button" aria-pressed="false">
      <span class="bars" aria-hidden="true"><i></i><i></i><i></i></span>
      <span id="aMuteLabel">Sound off</span>
    </button>
    <p id="aSoundNote">Drop an audio file, or the video's own sound is used.</p>
  </div>

  <section id="aintro" class="drop">
    <h2><?= cms_block('loopsmith-h2-drop-a-sound-to', 'Drop a sound to find where it loops') ?></h2>
    <p><?= cms_block('loopsmith-p-an-ambience-recording-almo', '
      An ambience recording almost never loops on its own — the end does not match
      the beginning, so you hear a click, a jump in level, or the same gust arriving
      twice. This compares every moment against every other by its spectrum, finds
      the closest pair, and closes the gap with an equal-power crossfade.
    ') ?></p>
    <p class="hint"><?= cms_block('loopsmith-hint-a-sample-is-loaded', 'Drop an audio file here, or the video\'s own sound is used.
      Nothing is uploaded. Decoding, analysis and export happen in this tab.') ?></p>
  </section>

  <section id="awork">
    <div class="tiles" id="atiles"></div>

    <div class="cols">
      <div>
        <h3 class="sec"><?= cms_block('loopsmith-sec-loop-map-2', 'Loop map') ?></h3>
        <div class="matrixbox">
          <canvas id="amatrix" width="10" height="10"></canvas>
          <div class="axis"><span>0s</span><span id="aaxmid">—</span><span id="aaxend">—</span></div>
        </div>
        <p class="note"><?= cms_block('loopsmith-note-every-analysis-window-comp', '
          Every analysis window compared against every other by its spectrum, not its
          waveform — two moments can look nothing alike sample by sample and still be
          the same sound. Bright means similar. Click a bright point away from the
          diagonal to select that window.
        ') ?></p>

        <h3 class="sec" style="margin-top:var(--s4)"><?= cms_block('loopsmith-sec-best-candidates-2', 'Best candidates') ?></h3>
        <div class="cands" id="acands"></div>
      </div>

      <div>
        <h3 class="sec"><?= cms_block('loopsmith-sec-loop', 'Loop') ?></h3>
        <div class="wavebox">
          <canvas id="awave" width="600" height="160"></canvas>
          <span class="wavenote" id="anote">No loop selected</span>
        </div>
        <div class="seam" id="aseam">Render a loop to check the seam.</div>
        <p class="note"><?= cms_block('loopsmith-note-the-crossfade-region-is', '
          The crossfade region is shaded. It plays as a genuine seamless loop — the
          browser wraps the buffer itself — so what you hear is what the file will do.
        ') ?></p>

        <h3 class="sec" style="margin-top:var(--s4)"><?= cms_block('loopsmith-sec-build-2', 'Build') ?></h3>
        <div class="fields">
          <div class="field">
            <label for="aStart">Start (s)</label>
            <input type="number" id="aStart" step="0.01" min="0" value="0">
          </div>
          <div class="field">
            <label for="aEnd">End (s)</label>
            <input type="number" id="aEnd" step="0.01" min="0" value="0">
          </div>
          <div class="field">
            <label for="aFade">Crossfade (ms)</label>
            <input type="number" id="aFade" step="10" min="0" value="250">
            <p class="desc"><?= cms_block('loopsmith-desc-longer-hides-a-worse-2', 'Longer hides a worse seam. Over about 2s it starts eating the loop.') ?></p>
          </div>
          <div class="field">
            <label for="aShape">Fade shape</label>
            <select id="aShape">
              <option value="pow" selected>Equal power</option>
              <option value="lin">Linear</option>
            </select>
            <p class="desc"><?= cms_block('loopsmith-desc-linear-dips-3-db', 'Linear dips ~3&nbsp;dB mid-fade on noise — audible as a breath.') ?></p>
          </div>
          <div class="field">
            <label for="aZero">Snap to zero crossing</label>
            <select id="aZero">
              <option value="1" selected>On</option>
              <option value="0">Off</option>
            </select>
            <p class="desc"><?= cms_block('loopsmith-desc-only-matters-at-0', 'Only matters at 0&nbsp;ms crossfade, where the join is a hard cut.') ?></p>
          </div>
          <div class="field">
            <label for="aNorm">Normalise</label>
            <select id="aNorm">
              <option value="0" selected>Off — leave the samples alone</option>
              <option value="1">Peak to −1 dBFS</option>
            </select>
            <p class="desc"><?= cms_block('loopsmith-desc-off-by-default-a', 'Off by default: a level change is still a change.') ?></p>
          </div>
          <div class="field">
            <label for="aHop">Analysis hop (ms)</label>
            <input type="number" id="aHop" step="10" min="10" max="500" value="40">
            <p class="desc"><?= cms_block('loopsmith-desc-finer-finds-tighter-window', 'Finer finds tighter windows and costs more time.') ?></p>
          </div>
          <div class="field">
            <label for="aBits">Export format</label>
            <select id="aBits">
              <option value="16" selected>WAV · 16-bit</option>
              <option value="24">WAV · 24-bit</option>
            </select>
            <p class="desc"><?= cms_block('loopsmith-desc-no-browser-encodes-mp3', 'No browser encodes mp3; encode from the WAV if you need one.') ?></p>
          </div>
        </div>

        <div class="actions">
          <button id="aBtnPlay">Play loop</button>
          <button id="aBtnRender">Render changes</button>
          <button id="aBtnExport" class="primary">Export WAV</button>
        </div>
        <div class="bar"><i id=abar></i></div>
        <p class="status" id="astatus">Ready.</p>
      </div>
    </div>

    <h3 class="sec" style="margin-top:var(--s5)"><?= cms_block('loopsmith-sec-same-build-in-python', 'Same build, in Python') ?></h3>
    <pre class="cli" id="acli">Select a loop window to generate the snippet.</pre>
    <p class="note"><?= cms_block('loopsmith-note-the-page-and-the', '
      The page and the snippet do the same arithmetic. The snippet is there for
      batching, for lengths that would be tedious to drag around, and because a loop
      you can regenerate from a script is a loop you still have next year.
    ') ?></p>
  </section>
</div>

<script>
(() => {
  "use strict";

  // ---------- state ----------
  const S = {
    file: null, url: null, video: null,
    fps: 24, n: 0, w: 0, h: 0,
    feat: [],           // Float32Array per frame, downsampled luma
    fw: 0, fh: 0,       // feature dims
    consec: 0, wrap: 0, drift: 0,
    matrix: null, maxDiff: 1,
    cands: [], sel: null,
    preview: null, playing: false, raf: 0,
    /* Bumped on every load. analyze() is slow and async — a hundred seeks
       on a long clip — and the demo below starts one at page load. Without
       this, dropping your own file during that window ends with the demo's
       results painted over your clip's. */
    gen: 0,
    isDemo: false,
  };

  const $ = id => document.getElementById(id);
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const fx = (v, d = 4) => Number(v).toFixed(d);

  function status(msg, kind) {
    const el = $("status");
    el.textContent = msg;
    el.className = "status" + (kind ? " " + kind : "");
  }
  function progress(p) { $("bar").style.width = clamp(p * 100, 0, 100) + "%"; }

  // ---------- file intake ----------
  $("file").addEventListener("change", e => {
    if (e.target.files && e.target.files[0]) load(e.target.files[0]);
  });
  const intro = $("intro");
  ["dragenter", "dragover"].forEach(t => intro.addEventListener(t, e => {
    e.preventDefault(); intro.classList.add("over");
  }));
  ["dragleave", "drop"].forEach(t => intro.addEventListener(t, e => {
    e.preventDefault(); intro.classList.remove("over");
  }));
  intro.addEventListener("drop", e => {
    const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) load(f);
  });
  document.querySelector(".filebtn").addEventListener("keydown", e => {
    if (e.key === "Enter" || e.key === " ") { e.preventDefault(); $("file").click(); }
  });

  async function load(file, isDemo) {
    stopPreview();
    const gen = ++S.gen;
    S.isDemo = !!isDemo;
    if (!isDemo) { const f = $("demoflag"); if (f) f.remove(); }
    S.file = file;
    if (S.url) URL.revokeObjectURL(S.url);
    S.url = URL.createObjectURL(file);

    const v = document.createElement("video");
    v.src = S.url; v.muted = true; v.playsInline = true; v.preload = "auto";

    // Resolve either way rather than rejecting: a codec this browser can't
    // decode is an expected outcome to report, not a crash.
    const ok = await new Promise(res => {
      v.onloadedmetadata = () => res(true);
      v.onerror = () => res(false);
    });

    if (gen !== S.gen) return;
    if (!ok || !v.videoWidth) {
      status("This browser can't decode that file. H.264 MP4 needs Chrome or Edge; WebM works in any browser.", "err");
      return;
    }

    S.video = v; S.w = v.videoWidth; S.h = v.videoHeight;
    $("fWidth").value = Math.min(720, S.w);
    await analyze(gen);
  }

  // ---------- frame sampling ----------
  function seek(v, t) {
    return new Promise(res => {
      const done = () => { v.removeEventListener("seeked", done); res(); };
      v.addEventListener("seeked", done);
      v.currentTime = t;
    });
  }

  async function analyze(gen) {
    if (gen == null) gen = S.gen;
    const v = S.video;
    S.fps = clamp(parseInt($("fFps").value, 10) || 24, 1, 60);
    const dur = v.duration;
    S.n = Math.max(2, Math.floor(dur * S.fps));

    // Feature resolution: small enough that an N-by-N comparison stays
    // interactive, large enough that structure survives.
    S.fw = 32;
    S.fh = Math.max(8, Math.round(32 * S.h / S.w));
    const c = document.createElement("canvas");
    c.width = S.fw; c.height = S.fh;
    const cx = c.getContext("2d", { willReadFrequently: true });

    S.feat = [];
    status("Reading frames…");
    for (let i = 0; i < S.n; i++) {
      // Half-frame offset lands inside the frame rather than on its edge.
      await seek(v, Math.min(dur - 1e-3, (i + 0.5) / S.fps));
      cx.drawImage(v, 0, 0, S.fw, S.fh);
      const d = cx.getImageData(0, 0, S.fw, S.fh).data;
      const g = new Float32Array(S.fw * S.fh);
      for (let p = 0, q = 0; p < d.length; p += 4, q++) {
        g[q] = (0.2126 * d[p] + 0.7152 * d[p + 1] + 0.0722 * d[p + 2]) / 255;
      }
      S.feat.push(g);
      if (i % 4 === 0) {
        progress(i / S.n * 0.7);
        await new Promise(r => setTimeout(r));
        if (gen !== S.gen) return;          // a newer file arrived
      }
    }
    if (gen !== S.gen) return;

    status("Comparing every frame against every other…");
    await new Promise(r => setTimeout(r, 16));
    computeStats();
    buildMatrix();
    findCandidates();
    renderTiles();
    drawMatrix();
    renderCandidates();
    progress(1);
    status("Analysis complete. Pick a bright point on the map, or a candidate below.", "good");
    if (S.cands.length) {
      select(S.cands[0].i, S.cands[0].j);
      /* Render the preview without being asked. The analysis is not worth
         much until you can watch the seam, and making that a button press
         is a step with no decision in it — you would press it every time.
         The button stays for re-rendering after a change. */
      await renderPreview(gen);
    }
    setTimeout(() => progress(0), 600);
  }

  function diff(a, b) {
    let s = 0;
    for (let k = 0; k < a.length; k++) s += Math.abs(a[k] - b[k]);
    return s / a.length;
  }

  function computeStats() {
    const d = [];
    for (let i = 0; i < S.n - 1; i++) d.push(diff(S.feat[i], S.feat[i + 1]));
    d.sort((x, y) => x - y);
    S.consec = d[Math.floor(d.length / 2)] || 1e-6;
    S.wrap = diff(S.feat[0], S.feat[S.n - 1]);

    const cov = f => { let c = 0; for (let k = 0; k < f.length; k++) if (f[k] > 0.30) c++; return c / f.length; };
    const a = cov(S.feat[0]), b = cov(S.feat[S.n - 1]);
    S.drift = a > 1e-6 ? (b - a) / a : 0;
  }

  function buildMatrix() {
    const n = S.n;
    const m = new Float32Array(n * n);
    let max = 0;
    for (let i = 0; i < n; i++) {
      for (let j = i; j < n; j++) {
        const v = diff(S.feat[i], S.feat[j]);
        m[i * n + j] = v; m[j * n + i] = v;
        if (v > max) max = v;
      }
    }
    S.matrix = m; S.maxDiff = max || 1;
  }

  function minLenFrames() { return Math.max(8, Math.round(1.5 * S.fps)); }

  function findCandidates() {
    const n = S.n, minLen = minLenFrames(), m = S.matrix;
    const all = [];
    for (let i = 0; i < n - minLen; i++) {
      for (let j = i + minLen; j < n; j++) all.push({ d: m[i * n + j], i, j });
    }
    all.sort((a, b) => a.d - b.d);
    const picked = [];
    for (const c of all) {
      if (picked.some(p => Math.abs(p.i - c.i) < S.fps * 0.4 && Math.abs(p.j - c.j) < S.fps * 0.4)) continue;
      picked.push(c);
      if (picked.length >= 8) break;
    }
    S.cands = picked;
  }

  // ---------- readout ----------
  function tile(lab, val, sub, pill) {
    return `<div class="tile"><span class="lab">${lab}</span>
      <div class="val">${val}</div>
      ${sub ? `<div class="sub">${sub}</div>` : ""}
      ${pill ? `<span class="pill ${pill[1]}">${pill[0]}</span>` : ""}</div>`;
  }

  function renderTiles() {
    const ratio = S.wrap / S.consec;
    const loops = ratio <= 1.3;
    const drift = S.drift;
    const steady = Math.abs(drift) < 0.08;
    $("tiles").innerHTML =
      tile("Frames", `${S.n}`, `${(S.n / S.fps).toFixed(2)}s at ${S.fps} fps · ${S.w}×${S.h}`) +
      tile("Frame-to-frame", fx(S.consec), "Median change between neighbours — the baseline everything is scored against.") +
      tile("Loops as-is", `${ratio.toFixed(2)}×`,
        `Wrap difference ${fx(S.wrap)}.`,
        loops ? ["Seamless", "ok"] : ratio < 2 ? ["Visible jump", "warn"] : ["Hard cut", "bad"]) +
      tile("Motion drift", `${(drift * 100 >= 0 ? "+" : "") + (drift * 100).toFixed(0)}%`,
        steady ? "Steady throughout — most windows will loop."
               : "The scene keeps building, so only a plateau will loop.",
        steady ? ["Steady", "ok"] : Math.abs(drift) < 0.4 ? ["Accumulating", "warn"] : ["Strongly accumulating", "bad"]);
  }

  function renderCandidates() {
    const box = $("cands");
    if (!S.cands.length) { box.innerHTML = `<div class="cand">No window long enough to loop.</div>`; return; }
    /* Coloured RELATIVE to the best window in the list, not against the
       1.3x / 2.0x scale used for finished loops.

       Those thresholds describe a loop after its dissolve. These numbers
       are the raw seam before it, which on real footage runs from 7x to
       40x even when the finished loop comes out at 0.01x. Painting the
       whole list red said "every option here is terrible" about material
       that closes perfectly, and that is a lie the tool was telling about
       its own results. Rank is the honest signal: this is the best window
       available, these are the near misses. */
    const best = S.cands[0].d / S.consec;
    box.innerHTML = S.cands.map((c, k) => {
      const r = c.d / S.consec;
      const rel = r / best;
      const cls = rel <= 1.12 ? "ok" : rel <= 1.5 ? "warn" : "bad";
      return `<button class="cand" role="option" aria-selected="false" data-k="${k}">
        <span>${(c.i / S.fps).toFixed(2)}s → ${(c.j / S.fps).toFixed(2)}s</span>
        <span class="len">${((c.j - c.i) / S.fps).toFixed(2)}s</span>
        <span class="ratio" style="color:var(--${cls})">${r.toFixed(2)}×</span>
      </button>`;
    }).join("");
    box.querySelectorAll(".cand[data-k]").forEach(b => b.addEventListener("click", () => {
      const c = S.cands[+b.dataset.k]; select(c.i, c.j);
    }));
  }

  // ---------- matrix ----------
  function drawMatrix() {
    const n = S.n, cv = $("matrix");
    cv.width = n; cv.height = n;
    const cx = cv.getContext("2d");
    const img = cx.createImageData(n, n);
    const minLen = minLenFrames();
    // Brass ramp: similar frames burn bright, dissimilar fall to the void.
    for (let i = 0; i < n; i++) {
      for (let j = 0; j < n; j++) {
        const v = S.matrix[i * n + j] / S.maxDiff;
        let s = Math.pow(1 - clamp(v, 0, 1), 3.2);           // similarity
        if (Math.abs(i - j) < minLen) s *= 0.14;             // too short to use
        const o = (i * n + j) * 4;
        img.data[o] = 8 + s * 242;
        img.data[o + 1] = 8 + s * 200;
        img.data[o + 2] = 11 + s * 110;
        img.data[o + 3] = 255;
      }
    }
    cx.putImageData(img, 0, 0);
    $("axmid").textContent = (S.n / S.fps / 2).toFixed(1) + "s";
    $("axend").textContent = (S.n / S.fps).toFixed(1) + "s";
    markSelection();
  }

  function markSelection() {
    if (!S.sel) return;
    const cv = $("matrix"), cx = cv.getContext("2d"), n = S.n;
    const { i, j } = S.sel;
    cx.strokeStyle = "#f2d98a";
    cx.lineWidth = Math.max(1, n / 220);
    cx.beginPath();
    cx.moveTo(0, j + .5); cx.lineTo(n, j + .5);
    cx.moveTo(i + .5, 0); cx.lineTo(i + .5, n);
    cx.globalAlpha = .55; cx.stroke(); cx.globalAlpha = 1;
    cx.beginPath(); cx.arc(i + .5, j + .5, Math.max(2, n / 60), 0, 7); cx.stroke();
  }

  $("matrix").addEventListener("click", e => {
    if (!S.n) return;
    const r = e.currentTarget.getBoundingClientRect();
    let i = Math.floor((e.clientX - r.left) / r.width * S.n);
    let j = Math.floor((e.clientY - r.top) / r.height * S.n);
    if (i > j) { const t = i; i = j; j = t; }
    if (j - i < minLenFrames()) { status("That window is too short to loop — pick a point further from the diagonal.", "warn"); return; }
    select(i, j);
  });

  // ---------- selection ----------
  function select(i, j) {
    S.sel = { i, j };
    showSeam(null);
    $("fStart").value = (i / S.fps).toFixed(2);
    $("fEnd").value = (j / S.fps).toFixed(2);
    const d = S.matrix[i * S.n + j], r = d / S.consec;
    drawMatrix();
    $("cands").querySelectorAll(".cand[data-k]").forEach(b => {
      const c = S.cands[+b.dataset.k];
      b.setAttribute("aria-selected", String(c.i === i && c.j === j));
    });
    $("pnote").textContent = `${((j - i) / S.fps).toFixed(2)}s loop · seam ${r.toFixed(2)}×`;
    status(`Window ${(i / S.fps).toFixed(2)}s → ${(j / S.fps).toFixed(2)}s selected. Render a preview or export.`);
    updateCli();
    stopPreview();
  }

  ["fStart", "fEnd", "fFade", "fCrush", "fGrade", "fWidth"].forEach(id =>
    $(id).addEventListener("change", updateCli));

  function updateCli() {
    const s = +$("fStart").value, e = +$("fEnd").value;
    if (!(e > s)) { $("cli").textContent = "Select a loop window to generate the command."; return; }
    const parts = [
      "python3 looper.py build INPUT.mp4 loop.mp4",
      `--start ${s.toFixed(2)}`, `--end ${e.toFixed(2)}`,
      `--fade ${parseInt($("fFade").value, 10) || 0}`,
    ];
    const cr = parseFloat($("fCrush").value);
    if (cr > 0) parts.push(`--crush ${cr}`);
    if ($("fGrade").value === "1") parts.push("--grade");
    parts.push(`--scale "${parseInt($("fWidth").value, 10)}:-2"`);
    $("cli").textContent = parts.join(" ");
  }

  // ---------- pixel processing ----------
  function processPixels(data, crush, grade) {
    const inv = crush > 0 ? 1 / (1 - crush) : 1;
    for (let p = 0; p < data.length; p += 4) {
      let r = data[p] / 255, g = data[p + 1] / 255, b = data[p + 2] / 255;
      if (crush > 0) {
        r = (r - crush) * inv; g = (g - crush) * inv; b = (b - crush) * inv;
        if (r < 0) r = 0; if (g < 0) g = 0; if (b < 0) b = 0;
      }
      if (grade) {
        const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        const w = clamp(1 - lum * 0.75, 0, 1);
        r = r * (1 - w) + r * 1.12 * w;
        g = g * (1 - w) + g * 0.90 * w;
        b = b * (1 - w) + b * 0.58 * w;
      }
      data[p] = clamp(r, 0, 1) * 255;
      data[p + 1] = clamp(g, 0, 1) * 255;
      data[p + 2] = clamp(b, 0, 1) * 255;
    }
  }

  function plan() {
    const s = +$("fStart").value, e = +$("fEnd").value;
    const i0 = Math.round(s * S.fps), i1 = Math.round(e * S.fps);
    const L = i1 - i0 + 1;
    const X = clamp(parseInt($("fFade").value, 10) || 0, 0, Math.max(0, L - 2));
    return {
      i0, i1, L, X, M: L - X,
      crush: clamp(parseFloat($("fCrush").value) || 0, 0, 0.3),
      grade: $("fGrade").value === "1",
    };
  }

  /**
   * Render the loop's frames.
   *
   * The dissolve is arranged so the wrap is frame-continuous:
   *   O[k] = lerp(S[M+k], S[k], k/X)  for k < X
   *   O[k] = S[k]                     for k >= X
   * O[M-1] is S[M-1] and O[0] blends in S[M] — adjacent source frames — so
   * playback wraps with no jump; the dissolve only masks the tail fading out.
   */
  async function renderFrames(width, onFrame, onProgress) {
    const p = plan();
    if (p.M < 2) throw new Error("That window is too short once the dissolve is subtracted.");
    const v = S.video, dur = v.duration;
    const w = Math.max(2, Math.round(width / 2) * 2);
    const h = Math.max(2, Math.round(w * S.h / S.w / 2) * 2);

    const cv = document.createElement("canvas");
    cv.width = w; cv.height = h;
    const cx = cv.getContext("2d", { willReadFrequently: true });

    /* A thumbnail of every frame we actually output, kept so the seam can
       be scored on the RESULT rather than on the source.

       This is the correction to a real mistake in how this tool read its
       own numbers. The candidate list ranks windows by the raw wrap
       difference — the seam BEFORE any dissolve — and that number is
       routinely 10x to 40x even on material that closes perfectly. It is
       a statement of the problem, not of the quality of the fix. Judging
       a clip by it means rejecting footage that loops beautifully.

       32x18 per frame is 576 samples, which is plenty to catch a seam and
       cheap enough that it costs nothing next to a video seek. */
    const fw = 32, fh = Math.max(8, Math.round(32 * h / w));
    const fc = document.createElement("canvas");
    fc.width = fw; fc.height = fh;
    const fcx = fc.getContext("2d", { willReadFrequently: true });
    const outFeat = [];

    const featureOf = () => {
      fcx.drawImage(cv, 0, 0, fw, fh);
      const d = fcx.getImageData(0, 0, fw, fh).data;
      const g = new Float32Array(fw * fh);
      for (let p = 0, q = 0; p < d.length; p += 4, q++) {
        g[q] = (0.2126 * d[p] + 0.7152 * d[p + 1] + 0.0722 * d[p + 2]) / 255;
      }
      return g;
    };

    const frameAt = async idx => {
      await seek(v, Math.min(dur - 1e-3, (idx + 0.5) / S.fps));
      cx.globalAlpha = 1;
      cx.drawImage(v, 0, 0, w, h);
    };

    // Tail frames are needed while the head plays, so capture them first.
    const tails = [];
    for (let k = 0; k < p.X; k++) {
      await frameAt(p.i0 + p.M + k);
      tails.push(await createImageBitmap(cv));
      onProgress && onProgress(k / Math.max(1, p.X) * 0.25);
    }

    const out = [];
    for (let k = 0; k < p.M; k++) {
      await frameAt(p.i0 + k);
      if (k < p.X) {
        const base = await createImageBitmap(cv);
        cx.globalAlpha = 1; cx.drawImage(tails[k], 0, 0);
        cx.globalAlpha = k / p.X; cx.drawImage(base, 0, 0);
        cx.globalAlpha = 1;
        base.close();
      }
      const img = cx.getImageData(0, 0, w, h);
      processPixels(img.data, p.crush, p.grade);
      cx.putImageData(img, 0, 0);
      outFeat.push(featureOf());
      const bmp = await createImageBitmap(cv);
      if (onFrame) await onFrame(bmp, k, p.M, cv, cx);
      else out.push(bmp);
      onProgress && onProgress(0.25 + (k + 1) / p.M * 0.75);
    }
    tails.forEach(t => t.close());

    /* Score the finished loop the same way the source was scored, so the
       two numbers are directly comparable: wrap difference over the median
       change between neighbouring frames. Under 1.0 the join changes less
       than an ordinary frame does, which is the definition of invisible. */
    let seam = null;
    if (outFeat.length > 2) {
      const dd = [];
      for (let k = 0; k < outFeat.length - 1; k++) dd.push(diff(outFeat[k], outFeat[k + 1]));
      dd.sort((a, b) => a - b);
      const consec = dd[Math.floor(dd.length / 2)] || 1e-9;
      const wrap = diff(outFeat[0], outFeat[outFeat.length - 1]);
      seam = { wrap, consec, ratio: wrap / consec };
    }
    return { frames: out, w, h, plan: p, seam };
  }

  // ---------- preview ----------
  function stopPreview() {
    S.playing = false;
    if (S.raf) cancelAnimationFrame(S.raf);
    S.raf = 0;
    if (S.preview) { S.preview.frames.forEach(f => f.close()); S.preview = null; }
  }

  async function renderPreview(gen) {
    if (gen == null) gen = S.gen;
    if (!S.sel) { status("Pick a loop window first.", "warn"); return; }
    stopPreview();
    setBusy(true);
    try {
      status("Rendering preview…");
      const r = await renderFrames(300, null, progress);
      if (gen !== S.gen) { r.frames.forEach(f => f.close()); return; }
      S.preview = r;
      const cv = $("pcanvas");
      cv.width = r.w; cv.height = r.h;
      const cx = cv.getContext("2d");
      let k = 0, last = 0;
      S.playing = true;
      const step = ts => {
        if (!S.playing || !S.preview) return;
        if (ts - last >= 1000 / S.fps) {
          cx.drawImage(S.preview.frames[k], 0, 0);
          k = (k + 1) % S.preview.frames.length;
          last = ts;
        }
        S.raf = requestAnimationFrame(step);
      };
      S.raf = requestAnimationFrame(step);
      showSeam(r.seam);
      status(`Preview looping — ${r.plan.M} frames (${(r.plan.M / S.fps).toFixed(2)}s). Watch the seam.`
             + (S.isDemo ? " This is the sample clip — drop your own on the panel above." : ""), "good");
    } catch (err) {
      status(err.message || "Preview failed.", "err");
    } finally { setBusy(false); progress(0); }
  }

  $("btnPreview").addEventListener("click", () => renderPreview());

  function setBusy(b) {
    $("btnPreview").disabled = b; $("btnExport").disabled = b;
  }

  /* The number that actually answers "did it work". The candidate ratio
     above it answers "how bad was it to begin with", and the two are
     routinely an order of magnitude apart — a window that ranks at 30x raw
     can close to 0.08x once the dissolve is applied. Reading the first as
     a verdict is how good footage gets thrown away. */
  function showSeam(sm) {
    const el = $("vseam");
    if (!el) return;
    if (!sm) { el.textContent = "Render a preview to score the seam."; el.className = "seam"; return; }
    const r = sm.ratio;
    const cls = r <= 1.0 ? "ok" : r <= 1.3 ? "ok" : r < 2 ? "warn" : "bad";
    const verdict = r <= 1.0 ? "the join changes less than an ordinary frame — invisible"
                  : r <= 1.3 ? "effectively seamless"
                  : r < 2 ? "a visible pulse each cycle — try a longer dissolve"
                  : "still a hard cut — try a longer dissolve or a different window";
    el.className = "seam " + cls;
    el.innerHTML = `Seam after the dissolve: <b>${r.toFixed(2)}×</b> — ${verdict}.
      <span style="color:var(--bone-faint)">Raw window before the dissolve was
      ${(S.sel ? (S.matrix[S.sel.i * S.n + S.sel.j] / S.consec).toFixed(2) : "—")}×.</span>`;
  }

  // ---------- export ----------
  function pickMime() {
    const want = [
      'video/mp4;codecs="avc1.42E01E"',
      "video/webm;codecs=vp9",
      "video/webm;codecs=vp8",
      "video/webm",
    ];
    for (const m of want) {
      if (window.MediaRecorder && MediaRecorder.isTypeSupported(m)) return m;
    }
    return null;
  }

  $("btnExport").addEventListener("click", async () => {
    if (!S.sel) { status("Pick a loop window first.", "warn"); return; }
    const mime = pickMime();
    if (!mime) { status("This browser can't encode video. Try Chrome, or use the command line below.", "err"); return; }

    stopPreview();
    setBusy(true);
    try {
      status("Encoding…");
      const width = clamp(parseInt($("fWidth").value, 10) || 720, 120, 2160);
      const bitrate = Math.round((parseFloat($("fRate").value) || 6) * 1e6);

      // Render every frame up front. This has to happen before recording
      // starts: MediaRecorder stamps each requested frame with the WALL
      // CLOCK time it arrived, and each frame costs a seek (~100ms+), so
      // feeding frames as they render stretches the result badly — a 2s
      // loop came out just under 7s.
      status("Rendering frames…");
      const r = await renderFrames(width, null, q => progress(q * 0.72));
      const frames = r.frames;

      const cv = document.createElement("canvas");
      cv.width = r.w; cv.height = r.h;
      const cx = cv.getContext("2d");

      // A 0 fps stream emits only the frames we ask for, so we control the
      // timeline — but we must still ask for them at real playback speed.
      const stream = cv.captureStream(0);
      const track = stream.getVideoTracks()[0];
      const rec = new MediaRecorder(stream, { mimeType: mime, videoBitsPerSecond: bitrate });
      const chunks = [];
      rec.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };
      const done = new Promise(res => { rec.onstop = res; });

      status("Encoding…");
      rec.start();
      const dt = 1000 / S.fps;
      const t0 = performance.now();
      for (let k = 0; k < frames.length; k++) {
        cx.drawImage(frames[k], 0, 0);
        track.requestFrame();
        progress(0.72 + (k + 1) / frames.length * 0.28);
        // Self-correcting pace so drift doesn't accumulate over the loop.
        const wait = t0 + (k + 1) * dt - performance.now();
        if (wait > 0) await new Promise(res => setTimeout(res, wait));
      }
      await new Promise(res => setTimeout(res, Math.max(150, dt * 2)));
      rec.stop();
      await done;
      track.stop();
      frames.forEach(f => f.close());

      const ext = mime.startsWith("video/mp4") ? "mp4" : "webm";
      const blob = new Blob(chunks, { type: mime });
      const mib = blob.size / 1048576;
      if (mib > 12) {
        status(`Encoded ${mib.toFixed(1)} MB — heavy for a web background. Lower the bitrate or width if this is going on a page.`, "warn");
      }

      const base = (S.file.name || "clip").replace(/\.[^.]+$/, "");
      const name = `${base}-loop.${ext}`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = name;
      document.body.appendChild(a);
      a.click();
      a.remove();
      // Revoke late: some browsers finish writing after the click returns.
      setTimeout(() => URL.revokeObjectURL(url), 15000);
      status(`Downloaded ${name} — ${mib.toFixed(1)} MB.`, "good");
    } catch (err) {
      status(err.message || "Export failed.", "err");
    } finally { setBusy(false); progress(0); }
  });

  /* ---------- the sample clip ----------------------------------------

     The page used to open as an empty drop zone, which showed a visitor
     nothing about what the tool does or whether it is worth finding a
     file for. When DEMO names a clip beside index.php, the page opens with it
     already analysed. The clip it was tuned on was a 2.5s generated clip,
     480px wide, VP9.

     38 KB, and VP9 rather than H.264 on purpose — this page's own warning
     is that Chromium builds without proprietary codecs cannot decode MP4,
     and a sample clip that fails to load in some browsers would
     demonstrate the opposite of what it is for.

     Deferred to idle: analysis is dozens of seeks and it must not be
     competing with first paint. If the fetch fails the page is exactly
     what it used to be, so there is nothing to handle. */
  const DEMO = null;      // set to "demo-clip.webm" after putting a sample beside index.php

  function loadDemo() {
    if (!DEMO) { const f = $("demoflag"); if (f) f.remove(); return; }
    fetch(DEMO)
      .then(r => (r.ok ? r.blob() : Promise.reject(new Error(r.status))))
      .then(b => {
        if (S.gen !== 0) return;            // the visitor got there first
        load(new File([b], DEMO, { type: b.type || "video/webm" }), true);
      })
      .catch(() => { const f = $("demoflag"); if (f) f.remove(); });
  }

  if (window.requestIdleCallback) requestIdleCallback(loadDemo, { timeout: 2500 });
  else setTimeout(loadDemo, 500);

  // Remember the last build settings; harmless if storage is unavailable.
  const KEYS = ["fFade", "fCrush", "fGrade", "fWidth", "fRate", "fFps"];
  try {
    const saved = JSON.parse(localStorage.getItem("loopsmith") || "{}");
    KEYS.forEach(k => { if (saved[k] != null) $(k).value = saved[k]; });
  } catch (e) { /* private mode, blocked storage — defaults are fine */ }
  KEYS.forEach(k => $(k).addEventListener("change", () => {
    try {
      const o = {}; KEYS.forEach(x => o[x] = $(x).value);
      localStorage.setItem("loopsmith", JSON.stringify(o));
    } catch (e) { /* ignore */ }
  }));
})();
</script>


<script>
/* =========================================================================
   Loopsmith · audio

   The video half above finds a loop by comparing frames. This finds one by
   comparing SPECTRA, and that difference is the whole reason it is separate
   code rather than a shared path with a flag.

   Two moments of a hiss can be completely uncorrelated sample by sample and
   be the same sound. Comparing waveforms would score them as maximally
   different and find nothing; comparing what frequencies are present, at
   what level, scores them as identical — which is what the ear does. So the
   feature per analysis window is a 24-band spectrum in dB, and everything
   downstream — the matrix, the candidate ranking, the seam ratio — is the
   same arithmetic the video half uses on downsampled luma.

   Deliberately a second IIFE with its own state. Generalising the working
   video tool to serve both was the alternative, and it would have put a
   `if (isAudio)` inside every function in a tool that already works.
   ========================================================================= */
(() => {
  "use strict";

  const A = {
    file: null, ctx: null, buf: null, mono: null,
    sr: 0, dur: 0, ch: 0,
    hop: 0, n: 0, feat: [], bands: 24,
    consec: 0, wrap: 0, drift: 0,
    matrix: null, maxDiff: 1,
    cands: [], sel: null,
    loop: null, src: null, master: null, playing: false, raf: 0,
      /* Off is the resting state. Nothing on this page makes a sound, or
       even runs an audio graph, until the switch is pressed. */
    muted: true,
    /* Bumped on every load. analysis is slow and async, and the sample
       below starts one at page load; without this, a file dropped during
       that window ends with the sample's results painted over it. */
    gen: 0,
    isDemo: false,
  };

  const $ = id => document.getElementById(id);
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const fx = (v, d = 2) => Number(v).toFixed(d);

  function status(msg, kind) {
    const el = $("astatus");
    el.textContent = msg;
    el.className = "status" + (kind ? " " + kind : "");
  }
  function progress(p) { $("abar").style.width = clamp(p * 100, 0, 100) + "%"; }

  /* ---------- FFT ----------------------------------------------------
     Iterative radix-2, in place. Written out rather than pulled in
     because this page has no dependencies and that is a feature: it is a
     single file you can drop on any host, or open from disk with no
     network at all. */
  function fft(re, im) {
    const n = re.length;
    for (let i = 1, j = 0; i < n; i++) {
      let bit = n >> 1;
      for (; j & bit; bit >>= 1) j ^= bit;
      j ^= bit;
      if (i < j) {
        let t = re[i]; re[i] = re[j]; re[j] = t;
        t = im[i]; im[i] = im[j]; im[j] = t;
      }
    }
    for (let len = 2; len <= n; len <<= 1) {
      const ang = -2 * Math.PI / len;
      const wr = Math.cos(ang), wi = Math.sin(ang);
      const half = len >> 1;
      for (let i = 0; i < n; i += len) {
        let cr = 1, ci = 0;
        for (let k = 0; k < half; k++) {
          const ar = re[i + k], ai = im[i + k];
          const br = re[i + k + half] * cr - im[i + k + half] * ci;
          const bi = re[i + k + half] * ci + im[i + k + half] * cr;
          re[i + k] = ar + br; im[i + k] = ai + bi;
          re[i + k + half] = ar - br; im[i + k + half] = ai - bi;
          const ncr = cr * wr - ci * wi;
          ci = cr * wi + ci * wr; cr = ncr;
        }
      }
    }
  }

  const FFTN = 2048;
  const win = new Float32Array(FFTN);
  for (let i = 0; i < FFTN; i++) win[i] = 0.5 - 0.5 * Math.cos(2 * Math.PI * i / FFTN);

  /* Log-spaced band edges. Log because pitch is, and because a linear
     split would put twenty of twenty-four bands above 5 kHz where almost
     nothing distinguishes one moment of a recording from another. */
  function bandEdges(sr, count) {
    const lo = 40, hi = Math.min(16000, sr / 2 * 0.95);
    const e = new Int32Array(count + 1);
    for (let b = 0; b <= count; b++) {
      const f = lo * Math.pow(hi / lo, b / count);
      e[b] = clamp(Math.round(f / sr * FFTN), 1, FFTN / 2 - 1);
    }
    for (let b = 1; b <= count; b++) if (e[b] <= e[b - 1]) e[b] = e[b - 1] + 1;
    return e;
  }

  function spectrum(mono, at, edges, out) {
    const re = new Float32Array(FFTN), im = new Float32Array(FFTN);
    for (let i = 0; i < FFTN; i++) {
      const k = at + i;
      re[i] = (k < mono.length ? mono[k] : 0) * win[i];
    }
    fft(re, im);
    for (let b = 0; b < out.length; b++) {
      let s = 0;
      for (let k = edges[b]; k < edges[b + 1]; k++) s += re[k] * re[k] + im[k] * im[k];
      const n = Math.max(1, edges[b + 1] - edges[b]);
      /* dB, floored at -80. A floor matters: without it two silent windows
         differ by tens of dB of numerical noise and the matrix lights up
         in exactly the places that are useless. */
      out[b] = Math.max(-80, 10 * Math.log10(s / n + 1e-12));
    }
  }

  /* ---------- intake ---------- */
  $("afile").addEventListener("change", e => {
    if (e.target.files && e.target.files[0]) load(e.target.files[0]);
  });
  const aintro = $("aintro");
  ["dragenter", "dragover"].forEach(t => aintro.addEventListener(t, e => {
    e.preventDefault(); aintro.classList.add("over");
  }));
  ["dragleave", "drop"].forEach(t => aintro.addEventListener(t, e => {
    e.preventDefault(); aintro.classList.remove("over");
  }));
  aintro.addEventListener("drop", e => {
    const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) load(f);
  });
  document.querySelector('label[for="afile"]').addEventListener("keydown", e => {
    if (e.key === "Enter" || e.key === " ") { e.preventDefault(); $("afile").click(); }
  });

  function ctx() {
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!A.ctx && AC) A.ctx = new AC();
    return A.ctx;
  }

  async function load(file, isDemo) {
    stop();
    const gen = ++A.gen;
    A.isDemo = !!isDemo;
    A.file = file;

    const c = ctx();
    if (!c) { status("This browser has no Web Audio support.", "err"); return; }

    status("Decoding…");
    let buf = null;
    try {
      const data = await file.arrayBuffer();
      buf = await new Promise((res, rej) => {
        /* Callback form as well as the promise: Safari shipped the
           callbacks years before it returned a promise. */
        const p = c.decodeAudioData(data, res, rej);
        if (p && p.then) p.then(res, rej);
      });
    } catch (err) {
      status("Could not decode that file. Try WAV, MP3, M4A, OGG or FLAC.", "err");
      return;
    }

    if (gen !== A.gen) return;
    A.buf = buf; A.sr = buf.sampleRate; A.dur = buf.duration; A.ch = buf.numberOfChannels;
    busy(false);

    /* Analysis is mono: a loop point is a property of the sound, not of
       where it sits in the image. The render keeps every channel. */
    const L = buf.length;
    const mono = new Float32Array(L);
    for (let c2 = 0; c2 < A.ch; c2++) {
      const d = buf.getChannelData(c2);
      for (let i = 0; i < L; i++) mono[i] += d[i] / A.ch;
    }
    A.mono = mono;

    await analyze(gen);
  }

  const MAXN = 420;   // matrix is n^2; past this the page stops feeling live

  async function analyze(gen) {
    if (gen == null) gen = A.gen;
    const wantHop = clamp(parseInt($("aHop").value, 10) || 40, 10, 500) / 1000;
    let hop = Math.max(1, Math.round(wantHop * A.sr));
    let n = Math.floor((A.mono.length - FFTN) / hop);

    if (n > MAXN) {
      /* Say so rather than silently taking four times as long. */
      hop = Math.ceil((A.mono.length - FFTN) / MAXN);
      n = Math.floor((A.mono.length - FFTN) / hop);
      $("aHop").value = Math.round(hop / A.sr * 1000);
      status(`Hop widened to ${Math.round(hop / A.sr * 1000)} ms to keep the map interactive.`, "warn");
    }
    if (n < 4) { status("Too short to loop — needs a couple of seconds at least.", "err"); return; }

    A.hop = hop; A.n = n;
    const edges = bandEdges(A.sr, A.bands);

    A.feat = [];
    status("Reading spectra…");
    for (let i = 0; i < n; i++) {
      const f = new Float32Array(A.bands);
      spectrum(A.mono, i * hop, edges, f);
      A.feat.push(f);
      if (i % 16 === 0) {
        progress(i / n * 0.6);
        await new Promise(r => setTimeout(r));
        if (gen !== A.gen) return;          // a newer file arrived
      }
    }
    if (gen !== A.gen) return;

    status("Comparing every window against every other…");
    await new Promise(r => setTimeout(r, 16));
    computeStats();
    buildMatrix();
    findCandidates();
    renderTiles();
    drawMatrix();
    renderCandidates();
    progress(1);

    if (A.cands.length) {
      select(A.cands[0].i, A.cands[0].j);
      /* Render the loop straight away — the analysis is worthless until
         you can see and hear the result, and making that a button press
         is a step with no decision in it. Playback is NOT started: sound
         that begins on its own is an ambush, and every browser blocks it
         anyway. */
      await render(true);
      /* Built, drawn, measured — and silent. Nothing is playing and no
         AudioContext is running until the switch is pressed.

         An earlier version started the loop muted at page load so that
         unmuting would be instant. That is worse mechanics for no real
         gain: it means a page that is quietly running an audio graph
         nobody asked for, and "muted" and "not playing" are the same
         thing to a visitor while being different things to debug. The
         switch starts it. */
      soundNote();
    } else {
      status("No window long enough to loop.", "warn");
    }
    setTimeout(() => progress(0), 600);
  }

  /* ---------- stats ---------- */
  function diff(a, b) {
    let s = 0;
    for (let k = 0; k < a.length; k++) s += Math.abs(a[k] - b[k]);
    return s / a.length;
  }

  function computeStats() {
    const d = [];
    for (let i = 0; i < A.n - 1; i++) d.push(diff(A.feat[i], A.feat[i + 1]));
    d.sort((x, y) => x - y);
    A.consec = d[Math.floor(d.length / 2)] || 1e-6;
    A.wrap = diff(A.feat[0], A.feat[A.n - 1]);

    /* Level drift, the audio analogue of the video half's motion drift.
       A recording that is quietly getting louder cannot loop anywhere
       except across a plateau, and no crossfade fixes it. */
    const rms = f => { let s = 0; for (let k = 0; k < f.length; k++) s += f[k]; return s / f.length; };
    const head = (rms(A.feat[0]) + rms(A.feat[1])) / 2;
    const tail = (rms(A.feat[A.n - 1]) + rms(A.feat[A.n - 2])) / 2;
    A.drift = tail - head;   // in dB
  }

  function buildMatrix() {
    const n = A.n, m = new Float32Array(n * n);
    let max = 0;
    for (let i = 0; i < n; i++) {
      for (let j = i; j < n; j++) {
        const v = diff(A.feat[i], A.feat[j]);
        m[i * n + j] = v; m[j * n + i] = v;
        if (v > max) max = v;
      }
    }
    A.matrix = m; A.maxDiff = max || 1;
  }

  function minLen() { return Math.max(4, Math.round(1.5 * A.sr / A.hop)); }

  function findCandidates() {
    const n = A.n, ml = minLen(), m = A.matrix, all = [];
    for (let i = 0; i < n - ml; i++) {
      for (let j = i + ml; j < n; j++) all.push({ d: m[i * n + j], i, j });
    }
    all.sort((a, b) => a.d - b.d);
    const picked = [];
    const near = Math.max(2, Math.round(0.4 * A.sr / A.hop));
    for (const c of all) {
      if (picked.some(p => Math.abs(p.i - c.i) < near && Math.abs(p.j - c.j) < near)) continue;
      picked.push(c);
      if (picked.length >= 8) break;
    }
    A.cands = picked;
  }

  const tSec = i => i * A.hop / A.sr;

  /* ---------- readout ---------- */
  function tile(lab, val, sub, pill) {
    return `<div class="tile"><span class="lab">${lab}</span>
      <div class="val">${val}</div>
      ${sub ? `<div class="sub">${sub}</div>` : ""}
      ${pill ? `<span class="pill ${pill[1]}">${pill[0]}</span>` : ""}</div>`;
  }

  function renderTiles() {
    const ratio = A.wrap / A.consec;
    const loops = ratio <= 1.3;
    const steady = Math.abs(A.drift) < 1.5;
    $("atiles").innerHTML =
      tile("Windows", `${A.n}`,
        `${A.dur.toFixed(2)}s · ${Math.round(A.hop / A.sr * 1000)}ms hop · ${A.sr / 1000}kHz ${A.ch === 1 ? "mono" : "stereo"}`) +
      tile("Window-to-window", `${fx(A.consec)} dB`,
        "Median spectral change between neighbours — the baseline everything is scored against.") +
      tile("Loops as-is", `${ratio.toFixed(2)}×`,
        `End-to-start difference ${fx(A.wrap)} dB.`,
        loops ? ["Seamless", "ok"] : ratio < 2 ? ["Audible jump", "warn"] : ["Hard cut", "bad"]) +
      tile("Level drift", `${(A.drift >= 0 ? "+" : "") + A.drift.toFixed(1)} dB`,
        steady ? "Steady throughout — most windows will loop."
               : "The level keeps moving, so only a plateau will loop.",
        steady ? ["Steady", "ok"] : Math.abs(A.drift) < 4 ? ["Drifting", "warn"] : ["Strongly drifting", "bad"]);
  }

  function renderCandidates() {
    const box = $("acands");
    if (!A.cands.length) { box.innerHTML = `<div class="cand">No window long enough to loop.</div>`; return; }
    /* Relative to the best window, for the same reason as the video half
       — see the note there. */
    const best = A.cands[0].d / A.consec;
    box.innerHTML = A.cands.map((c, k) => {
      const r = c.d / A.consec;
      const rel = r / best;
      const cls = rel <= 1.12 ? "ok" : rel <= 1.5 ? "warn" : "bad";
      return `<button class="cand" role="option" aria-selected="false" data-k="${k}">
        <span>${tSec(c.i).toFixed(2)}s → ${tSec(c.j).toFixed(2)}s</span>
        <span class="len">${(tSec(c.j) - tSec(c.i)).toFixed(2)}s</span>
        <span class="ratio" style="color:var(--${cls})">${r.toFixed(2)}×</span>
      </button>`;
    }).join("");
    box.querySelectorAll(".cand[data-k]").forEach(b => b.addEventListener("click", () => {
      const c = A.cands[+b.dataset.k]; select(c.i, c.j);
    }));
  }

  function drawMatrix() {
    const n = A.n, cv = $("amatrix");
    cv.width = n; cv.height = n;
    const cx = cv.getContext("2d");
    const img = cx.createImageData(n, n);
    const ml = minLen();
    for (let i = 0; i < n; i++) {
      for (let j = 0; j < n; j++) {
        const v = A.matrix[i * n + j] / A.maxDiff;
        let s = Math.pow(1 - clamp(v, 0, 1), 3.2);
        if (Math.abs(i - j) < ml) s *= 0.14;
        const o = (i * n + j) * 4;
        img.data[o] = 8 + s * 242;
        img.data[o + 1] = 8 + s * 200;
        img.data[o + 2] = 11 + s * 110;
        img.data[o + 3] = 255;
      }
    }
    cx.putImageData(img, 0, 0);
    $("aaxmid").textContent = (A.dur / 2).toFixed(1) + "s";
    $("aaxend").textContent = A.dur.toFixed(1) + "s";
    markSelection();
  }

  function markSelection() {
    if (!A.sel) return;
    const cv = $("amatrix"), cx = cv.getContext("2d"), n = A.n;
    const { i, j } = A.sel;
    cx.strokeStyle = "#f2d98a";
    cx.lineWidth = Math.max(1, n / 220);
    cx.beginPath();
    cx.moveTo(0, j + .5); cx.lineTo(n, j + .5);
    cx.moveTo(i + .5, 0); cx.lineTo(i + .5, n);
    cx.globalAlpha = .55; cx.stroke(); cx.globalAlpha = 1;
    cx.beginPath(); cx.arc(i + .5, j + .5, Math.max(2, n / 60), 0, 7); cx.stroke();
  }

  $("amatrix").addEventListener("click", e => {
    if (!A.n) return;
    const r = e.currentTarget.getBoundingClientRect();
    let i = Math.floor((e.clientX - r.left) / r.width * A.n);
    let j = Math.floor((e.clientY - r.top) / r.height * A.n);
    if (i > j) { const t = i; i = j; j = t; }
    if (j - i < minLen()) { status("That window is too short to loop — pick a point further from the diagonal.", "warn"); return; }
    select(i, j);
  });

  function select(i, j) {
    A.sel = { i, j };
    $("aStart").value = tSec(i).toFixed(2);
    $("aEnd").value = tSec(j).toFixed(2);
    const r = A.matrix[i * A.n + j] / A.consec;
    drawMatrix();
    $("acands").querySelectorAll(".cand[data-k]").forEach(b => {
      const c = A.cands[+b.dataset.k];
      b.setAttribute("aria-selected", String(c.i === i && c.j === j));
    });
    $("anote").textContent = `${(tSec(j) - tSec(i)).toFixed(2)}s loop · seam ${r.toFixed(2)}×`;
    status(`Window ${tSec(i).toFixed(2)}s → ${tSec(j).toFixed(2)}s selected. Render changes to hear it.`);
    updateCli();
  }

  /* ---------- building the loop ---------- */

  /* Snap to the nearest zero crossing. Only does anything at a crossfade
     of zero, where the join is a hard cut and a mid-waveform splice is an
     audible click. With any crossfade at all the join is continuous by
     construction and this is cosmetic. */
  function snapZero(pos, limit) {
    const m = A.mono;
    for (let d = 0; d < limit; d++) {
      for (const p of [pos + d, pos - d]) {
        if (p > 0 && p < m.length - 1 && m[p - 1] <= 0 && m[p] > 0) return p;
      }
    }
    return pos;
  }

  function plan() {
    const s = +$("aStart").value, e = +$("aEnd").value;
    let i0 = Math.round(s * A.sr), i1 = Math.round(e * A.sr);
    const xfMs = clamp(parseInt($("aFade").value, 10) || 0, 0, 5000);
    let X = Math.round(xfMs / 1000 * A.sr);
    if (X === 0 && $("aZero").value === "1") {
      const lim = Math.round(A.sr * 0.01);
      i0 = snapZero(i0, lim); i1 = snapZero(i1, lim);
    }
    const span = i1 - i0;
    X = clamp(X, 0, Math.max(0, Math.floor(span / 2) - 1));
    return { i0, i1, span, X, L: span - X,
             shape: $("aShape").value, norm: $("aNorm").value === "1" };
  }

  /* The loop itself.

       out[k]        = src[i0 + k]                                for k >= X
       out[k]        = src[i0+k]*up(k) + src[i0+L+k]*down(k)      for k <  X

     out[L-1] is src[i0+L-1] and out[0] blends in src[i0+L] — adjacent
     source samples — so playback wraps with no discontinuity. The
     crossfade masks the tail fading out, it does not create the join.

     Equal power (the square roots) rather than linear: two uncorrelated
     signals summed with linear ramps dip about 3 dB at the midpoint,
     which on a noise-like recording is plainly audible as a breath. */
  function buildLoop() {
    const p = plan();
    if (p.L < Math.round(A.sr * 0.1)) throw new Error("That window is too short once the crossfade is subtracted.");
    const c = ctx();
    const out = c.createBuffer(A.ch, p.L, A.sr);

    for (let ch = 0; ch < A.ch; ch++) {
      const src = A.buf.getChannelData(ch);
      const d = out.getChannelData(ch);
      for (let k = 0; k < p.L; k++) d[k] = src[p.i0 + k] || 0;
      for (let k = 0; k < p.X; k++) {
        const t = k / p.X;
        const up = p.shape === "pow" ? Math.sqrt(t) : t;
        const dn = p.shape === "pow" ? Math.sqrt(1 - t) : 1 - t;
        d[k] = (src[p.i0 + k] || 0) * up + (src[p.i0 + p.L + k] || 0) * dn;
      }
    }

    if (p.norm) {
      let peak = 0;
      for (let ch = 0; ch < A.ch; ch++) {
        const d = out.getChannelData(ch);
        for (let k = 0; k < d.length; k++) { const a = Math.abs(d[k]); if (a > peak) peak = a; }
      }
      if (peak > 0) {
        const g = 0.891 / peak;           // -1 dBFS
        for (let ch = 0; ch < A.ch; ch++) {
          const d = out.getChannelData(ch);
          for (let k = 0; k < d.length; k++) d[k] *= g;
        }
      }
    }
    return { buf: out, plan: p };
  }

  /* ---------- the seam verdict ----------------------------------------

     The measurement that matters is the single sample step that actually
     plays at the loop point — last sample to first — compared against the
     distribution of every ordinary adjacent step in the file.

     The obvious alternative is to compare the largest step in a window
     around the join against the largest in a window elsewhere. That
     throws false positives on any material with sparse loud transients:
     whichever window happens to contain a bigger one wins, and the result
     says nothing about the join. This tool got that wrong once already.  */
  function seamReport(buf) {
    const d = buf.getChannelData(0);
    const n = d.length;
    if (n < 64) return null;
    const join = Math.abs(d[0] - d[n - 1]);

    /* Sampled rather than exhaustive: a five-minute file is thirteen
       million steps and the percentile does not move after a few
       thousand. */
    const steps = [];
    const stride = Math.max(1, Math.floor(n / 8000));
    for (let i = stride; i < n; i += stride) steps.push(Math.abs(d[i] - d[i - 1]));
    steps.sort((a, b) => a - b);
    let below = 0;
    while (below < steps.length && steps[below] < join) below++;
    const pct = 100 * below / steps.length;
    return { join, pct, p99: steps[Math.floor(steps.length * 0.99)] || 0 };
  }

  function showSeam(rep) {
    const el = $("aseam");
    if (!rep) { el.textContent = "Render a loop to check the seam."; el.className = "seam"; return; }
    const cls = rep.pct < 90 ? "ok" : rep.pct < 99 ? "warn" : "bad";
    const verdict = rep.pct < 90 ? "an ordinary step — inaudible"
                  : rep.pct < 99 ? "larger than most steps — listen for it"
                  : "bigger than almost anything in the file — you will hear a click";
    el.className = "seam " + cls;
    el.innerHTML = `Join step <b>${rep.join.toFixed(5)}</b>, larger than <b>${rep.pct.toFixed(1)}%</b>
      of ordinary sample-to-sample steps (99th percentile is ${rep.p99.toFixed(5)}) — ${verdict}.`;
  }

  /* ---------- waveform ---------- */
  function drawWave(buf, xf) {
    const cv = $("awave");
    const w = cv.clientWidth || 600, h = 160;
    cv.width = w; cv.height = h;
    const cx = cv.getContext("2d");
    cx.fillStyle = "#000"; cx.fillRect(0, 0, w, h);

    const d = buf.getChannelData(0), n = d.length, mid = h / 2;

    if (xf > 0) {
      const bw = Math.max(1, xf / n * w);
      cx.fillStyle = "rgba(47,201,180,0.16)";
      cx.fillRect(0, 0, bw, h);
      cx.strokeStyle = "rgba(47,201,180,0.5)";
      cx.beginPath(); cx.moveTo(bw + .5, 0); cx.lineTo(bw + .5, h); cx.stroke();
    }

    cx.strokeStyle = "#2fc9b4";
    cx.beginPath();
    for (let x = 0; x < w; x++) {
      const a = Math.floor(x / w * n), b = Math.floor((x + 1) / w * n);
      let lo = 1, hi = -1;
      for (let i = a; i < b && i < n; i++) { const v = d[i]; if (v < lo) lo = v; if (v > hi) hi = v; }
      if (lo > hi) { lo = hi = 0; }
      cx.moveTo(x + .5, mid - hi * mid * 0.95);
      cx.lineTo(x + .5, mid - lo * mid * 0.95);
    }
    cx.stroke();

    cx.strokeStyle = "rgba(236,230,216,0.25)";
    cx.beginPath(); cx.moveTo(0, mid + .5); cx.lineTo(w, mid + .5); cx.stroke();
  }

  function drawPlayhead(frac) {
    if (!A.loop) return;
    drawWave(A.loop.buf, A.loop.plan.X);
    const cv = $("awave"), cx = cv.getContext("2d");
    const x = clamp(frac, 0, 1) * cv.width;
    cx.strokeStyle = "#f2d98a";
    cx.beginPath(); cx.moveTo(x + .5, 0); cx.lineTo(x + .5, cv.height); cx.stroke();
  }

  /* ---------- render / play / export ---------- */
  function busy(b) {
    $("aBtnPlay").disabled = b; $("aBtnRender").disabled = b; $("aBtnExport").disabled = b;
  }

  async function render(quiet) {
    if (!A.sel) { status("Pick a loop window first.", "warn"); return false; }
    const wasPlaying = A.playing;
    stop();
    busy(true);
    try {
      if (!quiet) status("Rendering…");
      await new Promise(r => setTimeout(r, 8));
      A.loop = buildLoop();
      drawWave(A.loop.buf, A.loop.plan.X);
      showSeam(seamReport(A.loop.buf));
      const secs = A.loop.buf.duration;
      $("anote").textContent = `${secs.toFixed(2)}s loop · ${Math.round(A.loop.plan.X / A.sr * 1000)}ms crossfade`;
      status(`Loop ready — ${secs.toFixed(2)}s. ${wasPlaying ? "Playing again." : "Press play to hear it."}`, "good");
      if (wasPlaying) play();
      return true;
    } catch (err) {
      status(err.message || "Render failed.", "err");
      return false;
    } finally { busy(false); progress(0); }
  }

  /* One gain between the loop and the speakers, owned by the mute switch
     alone. Ramped rather than switched: a step on a gain is a click, and
     on material with this much low end it is a thump. */
  function master() {
    const c = ctx();
    if (!A.master) {
      A.master = c.createGain();
      A.master.gain.value = A.muted ? 0 : 1;
      A.master.connect(c.destination);
    }
    return A.master;
  }

  function setMuted(m) {
    A.muted = !!m;
    const c = ctx();
    if (A.master) {
      const t = c.currentTime;
      A.master.gain.cancelScheduledValues(t);
      A.master.gain.setValueAtTime(A.master.gain.value, t);
      A.master.gain.linearRampToValueAtTime(A.muted ? 0 : 1, t + 0.12);
    }
    $("aMute").setAttribute("aria-pressed", String(!A.muted));
    $("aMuteLabel").textContent = A.muted ? "Sound off" : "Sound on";
    soundNote();
  }

  function soundNote() {
    const el = $("aSoundNote");
    if (!el) return;
    if (!A.loop) { el.textContent = "Load a sound and a loop is built for you."; return; }
    const secs = A.loop.buf.duration.toFixed(2);
    el.innerHTML = A.muted
      ? `A <b>${secs}s</b> loop is built and ready${A.isDemo ? " — this is the sample" : ""}. Press to hear it play seamlessly.`
      : `Playing a <b>${secs}s</b> loop${A.isDemo ? " — drop your own file over the sample" : ""}. The browser wraps the buffer, so this is exactly what the file does.`;
  }

  /* The switch is the transport. On starts the loop; off stops it — not
     "plays silence", which is the distinction that made the muted-from-
     load version worth abandoning. The gain ramp is still there because
     starting and stopping a bass-heavy loop on a hard edge is a thump. */
  $("aMute").addEventListener("click", () => {
    if (!A.loop) { status("Load a sound first.", "warn"); return; }
    if (A.muted) {
      ctx().resume();                    // this click is the gesture
      setMuted(false);
      play();
    } else {
      setMuted(true);
      /* Let the ramp finish before the source goes, or the fade is
         pointless. */
      setTimeout(() => { if (A.muted) stop(); }, 160);
    }
  });

  function play() {
    if (!A.loop) return;
    const c = ctx();
    stopSource();
    c.resume();
    const s = c.createBufferSource();
    s.buffer = A.loop.buf;
    s.loop = true;                       // the browser wraps the buffer itself
    s.connect(master());
    const t0 = c.currentTime;
    s.start();
    A.src = s; A.playing = true;
    $("aBtnPlay").textContent = "Stop";
    const dur = A.loop.buf.duration;
    const step = () => {
      if (!A.playing) return;
      drawPlayhead(((c.currentTime - t0) % dur) / dur);
      A.raf = requestAnimationFrame(step);
    };
    A.raf = requestAnimationFrame(step);
  }

  function stopSource() {
    if (A.src) { try { A.src.stop(); } catch (e) {} try { A.src.disconnect(); } catch (e) {} A.src = null; }
  }

  function stop() {
    A.playing = false;
    if (A.raf) cancelAnimationFrame(A.raf);
    A.raf = 0;
    stopSource();
    $("aBtnPlay").textContent = "Play loop";
    if (A.loop) drawWave(A.loop.buf, A.loop.plan.X);
    soundNote();
  }

  $("aBtnPlay").addEventListener("click", () => {
    if (A.playing) { stop(); setMuted(true); return; }
    ctx().resume();
    setMuted(false);
    if (!A.loop) { render(false).then(ok => { if (ok) play(); }); return; }
    play();
  });
  $("aBtnRender").addEventListener("click", () => render(false));

  /* Re-render on a change that alters the audio, so the waveform and the
     seam verdict are never describing a loop other than the one on
     screen. Start and End come from the map and already re-render. */
  ["aFade", "aShape", "aZero", "aNorm"].forEach(id =>
    $(id).addEventListener("change", () => { updateCli(); if (A.loop) render(false); }));
  ["aStart", "aEnd"].forEach(id =>
    $(id).addEventListener("change", () => { updateCli(); if (A.loop) render(false); }));
  $("aHop").addEventListener("change", () => { if (A.buf) analyze(); });

  /* ---------- WAV ----------------------------------------------------
     Written by hand because no browser will encode anything else. That is
     not a limitation worth apologising for: WAV is exactly right as the
     output of a tool whose whole purpose is a sample-accurate join, and
     an mp3 re-encode is somebody else's decision to make afterwards. */
  function wavBlob(buf, bits) {
    const ch = buf.numberOfChannels, n = buf.length, sr = buf.sampleRate;
    const bytesPer = bits / 8;
    const size = 44 + n * ch * bytesPer;
    const ab = new ArrayBuffer(size);
    const dv = new DataView(ab);
    const wr = (o, s) => { for (let i = 0; i < s.length; i++) dv.setUint8(o + i, s.charCodeAt(i)); };
    wr(0, "RIFF"); dv.setUint32(4, size - 8, true); wr(8, "WAVEfmt ");
    dv.setUint32(16, 16, true); dv.setUint16(20, 1, true); dv.setUint16(22, ch, true);
    dv.setUint32(24, sr, true); dv.setUint32(28, sr * ch * bytesPer, true);
    dv.setUint16(32, ch * bytesPer, true); dv.setUint16(34, bits, true);
    wr(36, "data"); dv.setUint32(40, n * ch * bytesPer, true);

    const data = [];
    for (let c = 0; c < ch; c++) data.push(buf.getChannelData(c));
    let o = 44;
    for (let i = 0; i < n; i++) {
      for (let c = 0; c < ch; c++) {
        const v = Math.max(-1, Math.min(1, data[c][i]));
        if (bits === 16) { dv.setInt16(o, v * 32767, true); o += 2; }
        else {
          const q = Math.round(v * 8388607);
          dv.setUint8(o, q & 255); dv.setUint8(o + 1, (q >> 8) & 255); dv.setUint8(o + 2, (q >> 16) & 255);
          o += 3;
        }
      }
    }
    return new Blob([ab], { type: "audio/wav" });
  }

  $("aBtnExport").addEventListener("click", async () => {
    if (!A.loop && !(await render(false))) return;
    busy(true);
    try {
      status("Writing WAV…");
      await new Promise(r => setTimeout(r, 8));
      const bits = parseInt($("aBits").value, 10) === 24 ? 24 : 16;
      const blob = wavBlob(A.loop.buf, bits);
      const base = (A.file.name || "sound").replace(/\.[^.]+$/, "");
      const name = `${base}-loop.wav`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url; a.download = name;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 15000);
      status(`Downloaded ${name} — ${(blob.size / 1048576).toFixed(2)} MB, ${bits}-bit.`, "good");
    } catch (err) {
      status(err.message || "Export failed.", "err");
    } finally { busy(false); progress(0); }
  });

  /* ---------- the same build, in Python ---------- */
  function updateCli() {
    const s = +$("aStart").value, e = +$("aEnd").value;
    if (!(e > s)) { $("acli").textContent = "Select a loop window to generate the snippet."; return; }
    const xf = (clamp(parseInt($("aFade").value, 10) || 0, 0, 5000) / 1000).toFixed(3);
    const pow = $("aShape").value === "pow";
    const norm = $("aNorm").value === "1";
    $("acli").textContent =
`import numpy as np, soundfile as sf

d, sr = sf.read("INPUT.wav", always_2d=True)
i0, i1 = int(${s.toFixed(2)} * sr), int(${e.toFixed(2)} * sr)
X = int(${xf} * sr)                     # crossfade
L = (i1 - i0) - X

loop = d[i0:i0 + L].copy()
t = (np.arange(X) / X)[:, None]
${pow ? "up, dn = np.sqrt(t), np.sqrt(1 - t)      # equal power" : "up, dn = t, 1 - t                        # linear"}
loop[:X] = d[i0:i0 + X] * up + d[i0 + L:i0 + L + X] * dn
${norm ? 'loop *= 0.891 / np.max(np.abs(loop))     # peak to -1 dBFS' : "# samples left untouched"}

# the check that matters: the step that actually plays at the join
join = np.abs(loop[0] - loop[-1]).max()
steps = np.abs(np.diff(loop[:, 0]))
print(f"join {join:.5f} = larger than {100 * (steps < join).mean():.1f}% of ordinary steps")

sf.write("loop.wav", loop, sr, subtype="PCM_${$("aBits").value === "24" ? "24" : "16"}")`;
  }

  /* ---------- the sample loop ----------------------------------------

     Set DEMO_AUDIO to null to open empty instead.

     Deferred to idle for the same reason as the video sample: decoding
     and a few hundred FFTs must not compete with first paint. A file
     dropped before it lands wins, via the generation counter. */
  const DEMO_AUDIO = null;      // set to "demo-audio.mp3" after putting a sample beside index.php

  function loadDemoAudio() {
    if (!DEMO_AUDIO) { idle(); return; }
    fetch(DEMO_AUDIO)
      .then(r => (r.ok ? r.blob() : Promise.reject(new Error(r.status))))
      .then(b => {
        if (A.gen !== 0) return;            // the visitor got there first
        return load(new File([b], DEMO_AUDIO, { type: b.type || "audio/mpeg" }), true);
      })
      .catch(() => {
        /* No sample is not an error state — it is how this half used to
           open. Put it back to the empty panel and say nothing. */
        idle();
        $("aSoundNote").textContent = "Drop a sound in and a loop is built for you.";
      });
  }

  /* Remember the build settings; harmless if storage is unavailable. */
  const KEYS = ["aFade", "aShape", "aZero", "aNorm", "aHop", "aBits"];
  try {
    const saved = JSON.parse(localStorage.getItem("loopsmith-audio") || "{}");
    KEYS.forEach(k => { if (saved[k] != null) $(k).value = saved[k]; });
  } catch (e) { /* private mode, blocked storage — defaults are fine */ }
  KEYS.forEach(k => $(k).addEventListener("change", () => {
    try {
      const o = {}; KEYS.forEach(x => o[x] = $(x).value);
      localStorage.setItem("loopsmith-audio", JSON.stringify(o));
    } catch (e) { /* ignore */ }
  }));

  window.addEventListener("resize", () => {
    if (A.loop && !A.playing) drawWave(A.loop.buf, A.loop.plan.X);
    else if (!A.buf) idleWave();
  });

  /* ---------- the empty state ----------------------------------------

     This half opens with no file in it, and the whole panel is on screen
     from the first paint. An empty panel that looks broken is worse than
     no panel: the tiles get their real labels with the values struck out,
     the map draws its own frame, and the buttons are visibly disabled
     rather than live and doing nothing. Somebody should be able to read
     what this does without loading anything. */
  function idleTiles() {
    $("atiles").innerHTML =
      tile("Windows", "—", "Length, hop and sample rate appear here.") +
      tile("Window-to-window", "—", "Median spectral change between neighbours — the baseline everything is scored against.") +
      tile("Loops as-is", "—", "Whether the file already wraps without help.") +
      tile("Level drift", "—", "Whether the level is steady enough for any window to loop.");
  }

  function idleMatrix() {
    const cv = $("amatrix");
    cv.width = 160; cv.height = 160;
    const cx = cv.getContext("2d");
    cx.fillStyle = "#0b0b0f"; cx.fillRect(0, 0, 160, 160);
    /* The diagonal, drawn faintly, because it is the one part of a
       similarity map that is the same for every file — a moment is always
       identical to itself. It reads as a hint about what will appear. */
    cx.strokeStyle = "rgba(47,201,180,0.28)";
    cx.beginPath(); cx.moveTo(0, 0); cx.lineTo(160, 160); cx.stroke();
    cx.fillStyle = "rgba(139,132,119,0.75)";
    cx.font = "7px monospace";
    cx.textAlign = "center";
    cx.fillText("every moment vs every other", 80, 96);
    $("aaxmid").textContent = "—";
    $("aaxend").textContent = "—";
  }

  function idleWave() {
    const cv = $("awave");
    const w = cv.clientWidth || 600, h = 160;
    cv.width = w; cv.height = h;
    const cx = cv.getContext("2d");
    cx.fillStyle = "#000"; cx.fillRect(0, 0, w, h);
    cx.strokeStyle = "rgba(236,230,216,0.18)";
    cx.beginPath(); cx.moveTo(0, h / 2 + .5); cx.lineTo(w, h / 2 + .5); cx.stroke();
  }

  function idle() {
    idleTiles();
    idleMatrix();
    idleWave();
    $("acands").innerHTML = `<div class="cand" style="color:var(--bone-dim)">Load a sound and the eight best windows are ranked here.</div>`;
    busy(true);
    status("Waiting for a file.");
  }

  idle();
  if (window.requestIdleCallback) requestIdleCallback(loadDemoAudio, { timeout: 3000 });
  else setTimeout(loadDemoAudio, 700);
})();
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.html'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/scripts.php'; ?>
</body>
</html>

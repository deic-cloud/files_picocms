---

Title: Service Briefing
Site: Sample Briefing
Theme: briefing
Access: public
eyebrow: Infrastructure Review
tag: Draft
date: Q3 2026
footer: Prepared for illustration only. Replace this content with your own briefing.

---

*This is a sample page for the **briefing** theme — a clean, print-friendly layout for
management reviews, service reports, and one-page decision memos. Everything below is
placeholder text you can replace. To edit, open the page in Files (button above) and edit
the Markdown, or drop your own `.md` files into the site folder.*

## Summary

State the recommendation in the first paragraph, before any detail. A reader who stops
here should still leave with the decision you want them to make. Keep it to two or three
sentences and let the sections below carry the evidence.

> **The one thing to remember.** Use a block-quote for the single line you most want the
> room to take away — it renders as a highlighted callout.

## Key figures

<div class="stats">
  <div class="stat"><div class="v">NN&nbsp;TB</div><div class="l">Capacity available</div></div>
  <div class="stat"><div class="v">NN%</div><div class="l">In use today</div></div>
  <div class="stat"><div class="v">N×</div><div class="l">Cost advantage</div></div>
  <div class="stat"><div class="v">0</div><div class="l">Egress fees</div></div>
</div>

## Options considered

| Option | Annual cost | Predictable? | Notes |
|---|---:|---|---|
| Do nothing | — | <span class="pill warn">No</span> | Cost drifts with demand |
| Managed external service | High | <span class="pill warn">Partly</span> | Per-seat, charged egress |
| This proposal | Low | <span class="pill good">Yes</span> | Capped and attributable |

Mark a numeric column with a right-aligned Markdown separator (`---:`) and the figures line
up on their decimals. Add `class="hi"` to a row (in an inline-HTML table) to emphasise the
recommended line.

## Headline number

<div class="price">
  <div class="big">€NN<span>per unit / year — all-in, no hidden fees</span></div>
  <ul>
    <li><b>Capped</b> — a hard ceiling, never a surprise bill</li>
    <li><b>Attributable</b> — every euro traces to who consumed it</li>
    <li><b>Portable</b> — open formats, no lock-in</li>
  </ul>
</div>

## Risks &amp; safeguards

<div class="cards">
  <div class="card risk">
    <p class="k">Risk</p>
    <h3>Continuity</h3>
    <p>Products get retired and data can be stranded. Mitigate with open, self-hosted formats.</p>
  </div>
  <div class="card risk">
    <p class="k">Risk</p>
    <h3>Runaway cost</h3>
    <p>Flat-rate storage lets a few users drive uncapped spend. Mitigate with hard per-user ceilings.</p>
  </div>
</div>

## Recommendation

1. Close the summary with a concrete next step, not a restatement.
2. Name who decides and by when.
3. Keep the whole briefing to one screen where you can — the theme is tuned for it, and
   prints cleanly to a single sheet.

---

*Placeholder content. The **briefing** theme styles standard Markdown (headings, tables,
lists, block-quote callouts) and offers opt-in `.stats`, `.price`, `.cards`, and `.pill`
blocks via inline HTML for richer one-pagers.*

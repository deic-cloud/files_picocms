---

Title: Configuration
Description: Options and sensible defaults
Author: 
Access: public
Theme: documentation
date: 2001-01-04

---

[Explain where configuration lives and when to touch it.]

| Option | Default | Description |
|--------|---------|-------------|
| `[threads]` | `4` | [What it controls and when to change it] |
| `[precision]` | `1e-6` | [Trade-off this option represents] |
| `[output_dir]` | `./results` | [Where results land] |

## Example

```ini
[run]
threads = 8
precision = 1e-9
```

[If your tool has formulas, inline math works: the default tolerance is
$\epsilon = 10^{-6}$, chosen so that $\|x_{n+1} - x_n\| < \epsilon$
terminates the iteration.]

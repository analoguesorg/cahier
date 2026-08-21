# Snips

**Snips** is a WordPress plugin built for analogues.org to handle custom interface elements, a live Discord chat panel, a dynamic date helper, an aviation weather easter egg, and an integrated discussion ledger.

---

## Modules

### 1. Telegrams & Field Ledger

* **What It Is:** An inquiry prompt and response module that pairs an editorial dispatch with a live discussion feed in a single slate card.
* **Why It Exists:** Standard comment sections feel separated from the text above them and require page reloads to post. The Field Ledger keeps the prompt, the ongoing discussion, and the input dock in one continuous container. Responses are submitted asynchronously via AJAX so the user never leaves the page.
* **How to Use It:**
  1. Go to **Telegrams → Add New Telegram** to draft and publish an inquiry.
  2. Add the **Snip Active Telegram** block to any page in the Gutenberg editor.
  3. Go to **Snips → Telegrams & Cadence** to manage the 4-slot dispatch conveyor:
     * **Slot 0:** Archive slot showing the most recently completed dispatch.
     * **Slots 1–3:** Assign upcoming telegrams with start and end dates. When Slot 1 expires, it archives into Slot 0, shifting Slots 2 and 3 forward.
     * **Indefinite State:** If no slots are active, the system automatically falls back to displaying the latest published Telegram with an `INDEFINITE // OPEN FORUM` status.

---

### 2. METAR Aviation Weather (Visual Easter Egg)

* **What It Is:** A lightweight easter egg shortcode set that pulls current aviation weather observations and displays them as clean visual elements.
* **Why It Exists:** It adds a subtle nod to general aviation and field instrumentation across site footers, sidebars, or headers without loading heavy third-party widgets. Data is cached in WordPress transients for 10 minutes to prevent unnecessary requests.
* **How to Use It:**
  * **Scrolling Ticker:** Renders a continuous horizontal ticker tape of one or more airport stations.
    ```text
    [snip_metar_ticker icao="KBOS,KORH,KMVY"]
    ```
  * **Flight Category Badge:** Outputs a simple colored badge indicating the current flight rule (VFR, MVFR, IFR, or LIFR).
    ```text
    [snip_metar_category icao="KBOS"]
    ```
  * **Raw Text:** Outputs the unformatted observation string.
    ```text
    [snip_metar_raw icao="KBOS"]
    ```

---

### 3. Discord Live Module

* **What It Is:** An embed container for WidgetBot live chat alongside a real-time presence indicator.
* **Why It Exists:** It lets visitors view active chat conversations or participate directly from the site without creating an account.
* **How to Use It:**
  1. Add your default Server ID under **Snips → Discord & Activity**.
  2. Insert the **Snip Discord Panel** block in Gutenberg. When placed in a multi-column layout, use the *Match Full Column Height (100%)* toggle to align it with adjacent content.
  3. Use the status shortcode to show live member activity:
     ```text
     [snip_discord_status server="YOUR_SERVER_ID"]
     ```

---

### 4. Dynamic Date & Typography

* **What It Is:** A helper utility that injects site-wide monospace font variables and renders server dates.
* **Why It Exists:** It ensures all components inherit the same monospace font stack defined in admin settings (`--snip-global-font` and `--snip-metar-font`) and provides a simple way to output formatted dates anywhere.
* **How to Use It:**
  * Define your font family in **Snips → Typography**.
  * Render the current date in any PHP date format:
    ```text
    [snip_date format="F j, Y"]
    ```

---

## Reference Table

### Gutenberg Blocks

| Block Name | Identifier | Description |
| :--- | :--- | :--- |
| **Snip Active Telegram** | `snips/active-telegram` | Displays the active inquiry prompt, status indicator, and asynchronous Field Ledger. |
| **Snip Discord Panel** | `snips/discord-frame` | Displays the WidgetBot chat frame with optional full-column height matching. |

### Shortcodes

| Shortcode | Parameters | Default | Description |
| :--- | :--- | :--- | :--- |
| `[snip_telegram_countdown]` | `href`, `prefix`, `suffix` | `href="/commons"` | Clickable badge displaying remaining dispatch time or indefinite forum status. |
| `[snip_telegram_stats]` | *None* | — | Outputs the total number of field notes logged on the active telegram. |
| `[snip_discord_status]` | `server`, `show_label` | `show_label="true"` | Displays a live presence dot and active user count. |
| `[snip_metar_ticker]` | `icao` | `icao="KBOS"` | Continuous scrolling ticker of METAR observations. |
| `[snip_metar_category]` | `icao` | `icao="KBOS"` | Colored flight category pill (VFR/MVFR/IFR/LIFR). |
| `[snip_metar_raw]` | `icao` | `icao="KBOS"` | Plain text observation string. |
| `[snip_date]` | `format` | WordPress default | Displays the current date in the specified PHP date format. |

---

## File Structure

```text
snips/
├── snips.php                        # Plugin bootstrap and version definitions
├── README.md                        # Documentation
├── includes/
│   ├── class-snips-admin.php        # Admin settings, conveyor matrix, and styles
│   ├── class-snips-date.php         # Date shortcode handler
│   ├── class-snips-metars.php       # METAR caching and visual shortcodes
│   ├── class-snips-discord.php      # Discord block rendering and presence polling
│   └── class-snips-telegrams.php    # Telegrams post type, conveyor engine, and AJAX ledger
└── assets/
    ├── css/
    │   ├── snips-admin.css          # Admin dashboard and slot conveyor styles
    │   ├── snips-metars.css         # Ticker tape animations and category badges
    │   ├── snips-discord.css        # Discord frame layout and status indicators
    │   └── snips-telegrams.css      # Ledger layout, input dock, and animations
    └── js/
        ├── snips-discord-block.js   # Gutenberg block editor code for Discord
        └── snips-telegrams-block.js # Gutenberg block editor code for Telegrams
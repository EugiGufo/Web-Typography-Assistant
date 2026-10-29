# Web Typography Assistant

**Web Typography Assistant** is a lightweight PHP and HTML tool for preparing text for publication on the web. It helps apply consistent typographic conventions and can optionally convert common web addresses and contact details into HTML links.

The interface runs in a browser; text processing is performed by PHP on the server.

## Features

Depending on the selected settings, the tool can:

- Apply typographic punctuation and spacing conventions.
- Choose between mnemonic and numeric HTML entities.
- Select styles for primary and nested quotation marks.
- Choose en dashes or em dashes.
- Handle line breaks as `<br>` elements or paragraphs.
- Convert URLs and bare domain names into links.
- Convert email addresses into `mailto:` links.
- Convert telephone numbers into `tel:` links.
- Convert suitable text lists into HTML lists.
- Preview the processed result and copy it for use elsewhere.
- Save interface settings in a browser cookie.

## Requirements

- A web server with PHP enabled.
- A modern web browser with JavaScript enabled.

The project consists of two files and does not require a database or third-party libraries.

## Installation

1. Download or clone this repository.
2. Upload `index.html` and `convert.php` to the **same directory** on a web server where PHP is enabled.
3. Open `index.html` through the web server in your browser.

Do not simply open `index.html` from your local filesystem: the page sends the text to `convert.php`, so the PHP endpoint must be served and executed by a web server.

Keep `convert.php` encoded as **UTF-8 without a byte-order mark (BOM)**. Its source file contains a warning about this requirement.

## Usage

1. Open the tool in your browser.
2. Choose the desired typography and HTML-output settings.
3. Enter or paste the text to process.
4. Select **Process the text**.
5. Review the original text and the processed preview.
6. Copy the result and use it in your publishing workflow.

The available settings determine which transformations are applied. Review the output before publishing, especially when processing HTML, code, or text with unusual formatting.

## Project files

- `index.html` — browser interface, settings, and client-side behaviour.
- `convert.php` — server-side text-processing functions.
- `README.md` — project documentation.
- `LICENSE` — GNU General Public License, version 3.
- `.gitignore` — excludes common local and temporary files from Git.

## Licence

This project is licensed under the **GNU General Public License, version 3 (GPL-3.0)**. You may use, study, share, and modify it under the terms of that licence. See the [`LICENSE`](LICENSE) file for the full text.

## Disclaimer

The tool is provided “as is”, without warranty of any kind, as described in the licence. Always review the processed text before publishing it.

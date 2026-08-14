# Bibliotech Library Block (`block_bibliotech`)

**Author**: Trevor McCready, Horizon Education Network (<https://www.horizonednet.org>)  
**License**: GNU General Public License v3 or later  
**Software Dependency**: Bibliotech (<https://bibliotechsl.com>)

---

## Overview

`block_bibliotech` is a Moodle Block plugin that provides an interactive widget on Dashboard and Course pages for launching Bibliotech resources. It integrates with the **Bibliotech** digital library platform (<https://bibliotechsl.com>).

### Key Functionality:
- **For Authorized Users** (users with the `bibliotech_subscriber` profile field enabled): Displays direct launch buttons to open the desktop/mobile application via `bibliotech://open` or launch the LTI Web Reader.
- **For Non-Subscribers**: Displays an informative call-to-action (CTA) card detailing Bibliotech's theological library resources with a direct link to subscribe at <https://bibliotechsl.com/subscribe/>.

---

## Prerequisites

This block requires the core integration plugin **`local_bibliotech`** to be installed and configured first.

---

## Installation Instructions

1. **Copy/Extract Plugin**:
   Extract or clone this directory into Moodle's `blocks/` directory:
   ```bash
   moodle/blocks/bibliotech
   ```
2. **Run Moodle Upgrade**:
   - Log in to your Moodle site as an Administrator.
   - Navigate to **Site Administration > Notifications** (or run `php admin/cli/upgrade.php` via command line).
   - Complete the database installation step.
3. **Add Block to Pages**:
   - Turn Edit Mode ON on your Dashboard or Course page.
   - Click **Add a block** and select **Bibliotech Library**.

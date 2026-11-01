# doliTrace

**doliTrace** is the specialized tracking and harvest management module for the **doliFarm** ecosystem. It provides granular logging of field operations, crop plans, and harvesting activities to ensure full traceability and regulatory compliance.

## 🌾 Core Functionalities
- **Operation Logging**: Record every field activity (tilling, irrigation, fertilization) with precision.
- **Harvest Management**: Track yields, quality metrics, and harvest dates linked to specific plots.
- **Crop Planning**: Manage long-term agricultural cycles and succession planting.
- **PWA (Field App)**: Includes a Progressive Web App for offline data entry directly from the field.

## 📱 PWA Features
- **Offline Mode**: Uses a Service Worker to allow data entry without an active internet connection.
- **Mobile First**: Optimized interface for smartphones used by field operators.

## 🏗 Technical Overview
- **Category**: Dolibarr Custom Module
- **Triggers**: Automated actions on crop status changes.
- **SQL**: Standardized schema for operations and harvest records (see `/sql`).

## 🚀 Installation
1. Move the `dolitrace` folder to your Dolibarr `htdocs/custom/` directory.
2. Enable the module in **Setup -> Modules**.
3. Access the **doliTrace Index** from the main menu to start logging.

## 📄 License
Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>

This module is free software, released under the **GNU Affero General Public License version 3 or later** (AGPL-3.0-or-later, text in `COPYING` and `LICENSE`). If you run a modified version for users over a network (for example as a SaaS), you must offer them its source code.

Some files are derived from the ModuleBuilder templates of Dolibarr and keep the notice of their authors under the **GNU General Public License version 3 or later** (text in `COPYING-GPL-3.0`). The module as a whole is distributed under the AGPL, as allowed by section 13 of both licenses.

"doliFarm" and "doliTrace" are names of doliFarm.com: the license covers the code, not the names.

---
Developed by **Luigi Grillo**.

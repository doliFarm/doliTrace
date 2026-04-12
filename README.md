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

---
Developed by **Luigi Grillo**.

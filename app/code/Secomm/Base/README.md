# Secomm_Base

Base / admin-shell module for the Secomm module family. Provides the shared admin menu and the "Secomm Extensions" system-config tab, plus shared helpers used across Secomm modules.

## What it does
- Registers the **"Secomm" admin menu** + "CORE" section (parent menu for Secomm modules' admin pages).
- Adds the **"Secomm Extensions" system configuration tab** (Stores → Configuration) where Secomm modules expose their settings.
- Shared helpers + plugins reused by other Secomm modules.

## Related
- Other Secomm modules (`AddressDropdown`, `VietNamAddress`, `VietNamMarket`) use this shared admin shell.

# before-dependency-install


- **Mục đích**: Dependency = supply-chain risk → provenance + advisory + TL approval trước install.
- **Khi trigger**: Trước `composer require` / `npm install <new>` / install module/app.
- **Checklist**: [`../../checklists/before-dependency-install-checklist.md`](../../checklists/before-dependency-install-checklist.md)
- **Evidence yêu cầu**: Provenance + advisory check; TL approval (Hard Gate); lockfile updated.
- **Xử lý khi fail**: Không install — tìm alternative hoặc escalate.


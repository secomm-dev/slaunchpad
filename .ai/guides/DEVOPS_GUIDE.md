# DevOps Guide — Secomm Launchpad

<!-- Enablement artifact — output: .ai/guides/DEVOPS_GUIDE.md (VI). Role: DevOps. -->

> **Purpose:** Hướng dẫn DevOps — CI/CD, môi trường, deploy, rollback, secret.
> **Human Owner:** DevOps · **Review Required:** None · **Approval Required:** No
> **Relevant Sections:** "🧭 Tình huống thực tế" + "Production readiness" · **Expected Reading Time:** 8 min
> **Current Status:** Generated · **Next Step:** Mở `../runtime/PROJECT_NAVIGATOR.md`

## Role responsibilities

Bạn **own operational execution**: CI/CD, môi trường (local/staging/production), deploy, rollback, secret/env management. Bạn **execute** deploy/rollback (không approve deployment — đó là TL/SA). Secret rotation execute nhưng trigger/approval từ SA/TL. **Không put secret vào code/log/prompt.**

**Trong dự án vừa khởi tạo**, đây là role **phụ trách blocking decision lớn nhất**: hạ tầng production chưa định nghĩa (Redis, Varnish, OpenSearch, CI/CD). Chốt xong thì project mới go-live được.

## Supported intents

| Intent | Bạn nói | Command | Bạn nhận được |
|---|---|---|---|
| Sinh deployment checklist | "sinh deployment checklist" | `/deploy` | Pre-deploy + deploy + post-deploy + rollback (trigger cụ thể) |
| Xem quyết định chờ | "phải approve gì" | `/decisions` | Decision hạ tầng/CI đang chờ |
| Approve decision (hạ tầng) | "tôi đồng ý opt-b" | `/approve` | Decision chốt → Navigator cập nhật |
| Impact checkout/payment | "deploy change X ảnh hưởng gì?" | `/magento-checkout-impact` | Flow critical cần smoke test |
| Security review (secret) | "review bảo mật change này" | `/security-review` | Secret/permission findings |
| Tóm tắt status | "tóm tắt status dự án" | `/status` | Deploy readiness summary |

## Typical workflow (business language)

**Discovery → Spec → Build → Review → Deploy**

Bạn vào ở phase **Deploy**: nhận approved checklist + rollback + release window từ TL → merged branch + tested migration từ Developer/QC → execute deploy → post-deploy smoke test (cover critical flow: checkout/payment/search) → monitoring watch window → deployment readiness report cho TL (TL approve final).

## 🧭 Tình huống thực tế

| Tình huống | Bạn nói | AI làm gì | Kết quả mong đợi |
|---|---|---|---|
| Chốt hạ tầng production | "phải approve gì" | Decision Redis/Varnish/OpenSearch/CI + option | Bạn approve option → go-live path rõ |
| Chuẩn bị deploy | "sinh deployment checklist" | Pre-deploy + rollback + post-deploy | Checklist có rollback trigger cụ thể |
| Deploy change chạm checkout | "deploy change X ảnh hưởng gì?" | Flow critical (checkout/payment/search) | Smoke test plan cụ thể |
| CI/CD failure | (NL) — mô tả log | Phân tích failure CI/CD | Root cause + fix gợi ý |
| Post-deploy verify | "verify sau deploy" | Smoke test critical flow | Pass/fail + watch window |

## Production readiness (project-specific — BLOCKING cho go-live)

| Yêu cầu | Hiện trạng | Phải làm |
|---|---|---|
| **OpenSearch** | Chưa cấu hình (client libs có nhưng inactive) | Magento 2.4.8 **yêu cầu** OpenSearch — cấu hình ở production env |
| **Redis (cache + sessions)** | File cache + file sessions (local-dev only) | Production **phải** dùng Redis (cache + sessions) |
| **Varnish (FPC)** | Không có | Cấu hình Varnish cho Full Page Cache |
| **CI/CD** | Không commit pipeline | Bitbucket Pipelines (repo host = Bitbucket) — `[TBD]` |
| **staging env** | Không | Tạo staging trước production |
| **Hyvä Packagist token** | Phụ thuộc token `auth.json` | Giữ token hợp lệ; document trong deployment |
| **env.php** | Local-dev config trong repo | **Đừng commit** production env.php / credentials |

## Deploy smoke test (critical flow)
- Checkout end-to-end (Mageplaza OSC) + payment (Mollie active).
- Search (sau khi OpenSearch cấu hình).
- Address dropdown VN cascade.
- Cron (AbandonedCart mỗi phút — theo dõi throughput).

## Expected outputs
- Deployment checklist (generated + reviewed).
- Rollback plan: **trigger cụ thể** + step + post-verify.
- Post-deploy smoke test result.
- Deployment readiness recommendation (TL approve final).
- CI/CD failure analysis (nếu có).

## Common mistakes

| Mistake | Fix |
|---|---|
| Rollback "if something goes wrong" | Trigger **cụ thể** (vd: error rate > X% trong 5 phút) + post-verify |
| Commit production env.php | Repo chỉ giữ local-dev config; prod env tách riêng |
| Deploy mà không smoke test checkout | Smoke test bắt buộc: checkout + payment + search |
| Quên watch window sau deploy | Monitoring watch window sau deploy (đặc biệt cron throughput) |

## Best practices
- [ ] Rollback trigger specific + có post-verify.
- [ ] Migration test trên staging trước production.
- [ ] Smoke test cover critical flow (checkout/payment/search).
- [ ] Monitoring watch window sau deploy.
- [ ] No secret trong generated output / log / prompt.

> Phần kỹ thuật nội bộ được xử lý tự động — bạn execute deploy bằng business language.

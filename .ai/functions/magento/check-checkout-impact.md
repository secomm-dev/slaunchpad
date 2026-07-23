# check-checkout-impact (Magento)

> Function (VI guidance). Magento project only. HIGH-RISK — Tier 2.

## Mục đích
Check checkout/payment/shipping/order impact của một change — totals calculation, payment method, shipping carrier, order state machine. Orchestrator cho `magento-checkout-impact` skill.

## Trigger
- Prompt snippet: "Check checkout impact của change {diff}: totals/payment/shipping/order state. Read 10. Tier 2 nếu touch payment/checkout."

## Required inputs
- Change diff (checkout/payment/shipping/order area)

## Required project files to read
- `project-context/10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md`, `09`

## Dependencies
- Skill: `magento-checkout-impact`
- Agent: magento-reviewer, security-reviewer (payment)
- Rule: `security-first.md`, `backward-compatibility.md`
- Escalation: **Tier 2 bắt buộc**

## Execution steps
1. Map change vs checkout flow (totals → payment → shipping → order).
2. Check totals calculation (price/tax/discount order; rounding).
3. Check payment method (capture/authorize; double charge; gateway).
4. Check shipping carrier/rate.
5. Check order state transition.
6. Finding + Tier 2 escalation.

## Expected output
Checkout impact report: affected step + risk (revenue/data) + Tier 2 flag + test scenario.

## Evidence required
Report lưu `.ai/evidence/{task}/checkout-impact.md` + Tier 2 signoff.

## Memory files to update
- `project-context/06`, `10`, `LESSONS_LEARNED.md`

## Failure handling
- Touch payment/checkout/order → **Tier 2 escalation trước implement**.
- Revenue/data risk (S0) → STOP, escalate.

## When to improve/update
- Khi checkout pattern mới (e.g., new payment gateway) → record + update 10.

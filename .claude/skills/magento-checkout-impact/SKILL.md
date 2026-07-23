# Magento Checkout Impact

## Purpose

Assess the impact of changes to checkout, payment, shipping, or order flow. Maps the current flow, identifies what the proposed change touches, and lists regression risks specific to these critical eCommerce areas.

## When to Use

- Before making any change to checkout, payment, shipping, or order processing
- Before modifying a plugin/observer on checkout/payment/shipping classes
- Before adding or modifying a payment gateway module
- Before changing order state machine or status transitions
- When debugging checkout/payment/shipping/order issues

## Prerequisites

- `project-context/10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md` — current flow documentation
- `project-context/09_MAGENTO_MODULE_MAP.md` — modules that affect these flows
- `project-context/02_BUSINESS_RULES.md` — rules for pricing, discounts, shipping, tax
- Description of the proposed change

## Input

Description of the proposed change to checkout, payment, shipping, or order flow.

## Steps

1. Read `project-context/10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md` — understand the current flow
2. Read `project-context/09_MAGENTO_MODULE_MAP.md` — identify which modules affect the flow
3. Map the current flow:
   - **Checkout steps**: What are the steps? Any custom steps? What controllers/actions handle each?
   - **Payment methods**: What methods are available? What gateways? Capture flow?
   - **Shipping methods**: What carriers? Rate calculation logic?
   - **Order states**: State machine diagram? Custom transitions?
4. Identify what the proposed change affects — trace through the flow and mark impact points
5. Check for plugin/observer chains on affected areas — use `magento-module-analysis` for specific modules
6. List regression risks specific to:
   - Price calculation (discounts, taxes, shipping)
   - Payment processing (capture, refund, partial payment)
   - Order state transitions (cancel, return, partial shipment)
   - Integration sync (ERP, accounting, inventory)
7. Recommend test scenarios covering the affected flows

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Checkout Impact Analysis

### Change Description
{what is being changed}

### Current Flow Map
{checkout steps, payment methods, shipping, order states}

### Impact Assessment
| Flow Area | Affected? | Details |
|-----------|-----------|---------|
| Checkout steps | Yes/No | {details} |
| Payment | Yes/No | {details} |
| Shipping | Yes/No | {details} |
| Order states | Yes/No | {details} |

### Plugin/Observer Chains Affected
| Module | Interaction | Impact |
|--------|-------------|--------|
| {module} | {plugin/observer} | {impact} |

### Regression Risks
- {risk}

### Recommended Test Scenarios
1. {test scenario}
```

## Quality Checklist

- [ ] Current flow accurately mapped from 10_CHECKOUT_PAYMENT_SHIPPING_ORDER_FLOW.md
- [ ] All modules that touch affected areas identified
- [ ] Regression risks include: price, payment, order state, integration sync
- [ ] Test scenarios cover: happy path, error states, edge cases per flow area

## Escalation Rules

- ALWAYS Tier 2 — checkout/payment/shipping/order changes require SA/CTO review
- If the change modifies order state machine → flag for SA review
- If the change adds/removes payment methods → flag for SA/CTO review

## Example

**Input**: "Change the B2B approval flow — orders over $10,000 (was $5,000) need manager approval"

**Output**:
```markdown
## Checkout Impact Analysis

### Change Description
Raise B2B order approval threshold from $5,000 to $10,000.

### Current Flow Map
Custom checkout → B2B fields → order total check → if >$5,000 → pending_approval status → email manager

### Impact Assessment
| Flow Area | Affected? | Details |
|-----------|-----------|---------|
| Checkout steps | No | Same flow, different threshold value |
| Payment | No | No change to payment processing |
| Order states | Yes | pending_approval trigger threshold changes |
| ERP sync | No | No change — ERP already handles new status |

### Plugin/Observer Chains Affected
| Module | Interaction | Impact |
|--------|-------------|--------|
| Acme\CustomCheckout | Plugin on QuoteManagement::submit | Approval check threshold change |

### Regression Risks
- Orders between $5k-$10k will now go through without approval — is this intended?

### Recommended Test Scenarios
1. Test order $9,500 — should pass without approval (new threshold)
2. Test order $10,500 — should trigger approval (above threshold)
3. Test order $5,500 — should pass without approval (was previously blocked)
```
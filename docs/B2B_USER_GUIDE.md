# B2B stock and manually priced sales

Your manufacturing flow is **buy raw materials -> make parts -> assemble finished products**. Each part has its own BOM and production order. The finished-product BOM consumes the produced parts. Assembly does not consume their raw materials again.

Finished production is received into main stock. You then choose how much of that stock to allocate to B2B.

## Example

| Action | Main stock | B2B stock | Total company stock |
| --- | ---: | ---: | ---: |
| Receive 500 finished bulbs | 500 | 0 | 500 |
| Transfer 200 to B2B | 300 | 200 | 500 |
| Sell 10 through B2B | 300 | 190 | 490 |
| Transfer 50 unsold units back to main | 350 | 140 | 490 |

A transfer moves stock. It does not create sales revenue, an invoice, or a customer payment. No selling price is needed for a transfer. The existing inventory cost follows the goods.

## 1. Move selected products into B2B

Open **B2B Management -> Stock Transfers -> Transfer stock**. You can also click Transfer beside a product on the B2B Products page.

1. Choose the main warehouse.
2. Choose **Main stock -> B2B stock**.
3. Search for a product or SKU. For variable products, select the actual variation.
4. Enter the quantity to move. The form shows its main and B2B quantities.
5. Click Add product to move several different products together.
6. Add notes if useful, then confirm Transfer stock.

The quantities are deducted from main stock and added to that warehouse's B2B stock together. Insufficient stock stops the entire transfer. An administrator can enable a selected product for B2B as part of its first transfer. A transfer-only operator can transfer products already enabled by a product manager.

The same master product is reused. You do not need to create another copy of a manufactured bulb.

The Products page also offers Enable B2B / Disable B2B. Those buttons control whether a product is eligible for B2B; they do not move any quantity. Use Transfer stock to allocate stock.

## 2. Add the business customer

Open **B2B Management -> Business Customers**.

- Existing contacts appear in the list. Click Enable for B2B to reuse one.
- For a new buyer, click Add business customer and enter the business name, phone, optional email/address, B2B credit limit and payment term.
- A B2B credit limit of 0 requires fully paid sales. Enter a positive limit to permit credit, subject to the operator's credit-sale permission.

The credit limit applies to B2B outstanding invoices. It does not replace the existing retail customer's terms or balance. New customers use the existing commercial Contact records, not distribution employee stock accounts.

## 3. Sell with a manually entered price

Open **B2B Management -> B2B POS**.

1. Choose the warehouse whose B2B stock you want to sell.
2. Select the business customer.
3. Search and add products from B2B stock.
4. Enter the sale quantity.
5. Enter **Manual unit price** for every line. This field starts blank.
6. Enter a payment, use Pay full total, or add split payments. Leave payments at 0 for a permitted credit sale.
7. Confirm Complete B2B sale, then open/print the invoice.

Your manually entered price is the final unit price. No separate tax or discount is automatically added. For example, 10 bulbs at a manually entered price of 85 produces a total of 850.

The existing retail selling price is not copied into this field and is not changed by your B2B sale. Inventory cost is retained separately for cost/profit reporting.

The sale deducts B2B stock only. It cannot use additional main stock to cover a B2B shortage. Transfer more quantity first if necessary.

## 4. Invoices and payments

- **Sales & Invoices** lists B2B sales, paid amounts, returns and remaining due.
- Open an invoice to print it or use the browser's Save PDF option.
- **Payments & Dues** lists unpaid/partially paid B2B invoices. Click Record payment to collect more money.
- Payments may be split between enabled methods. Select the payment account where applicable; configured warehouse defaults are suggested.
- A payment cannot exceed the invoice's remaining balance. Credit permission does not override the customer's credit limit.
- Posted prices and product/customer descriptions are preserved in the invoice even if master records later change.

Recording a payment against an account uses the existing payment event/account transaction integration. It is not a second independent B2B payment ledger.

## 5. Return goods or move unsold stock back

These are different actions:

**Customer returns:** On Sales & Invoices click Return items. Enter each returned quantity and a reason. The screen previews the return credit and any required refund. Unpaid invoice amounts are reduced first. If collections exceed the remaining invoice value, record the exact required refund in the same action. Returned goods go back into B2B stock at their original inventory cost. You cannot return more than the net quantity sold.

**Unsold stock back to main:** Open Transfer stock and choose **B2B stock -> Main stock**. Select the products and quantities. Main stock increases and B2B stock decreases. This is not a refund or a sale return.

Disabling a B2B product does not erase its stock. Its remaining quantity can still be transferred back to main. Archived master products can be found in B2B Inventory and in product search when transferring back to main.

## 6. History, reports and access

- **B2B Inventory:** enrolled products with their main and B2B quantities.
- **Stock Transfers:** transfer references, direction, warehouse and selected quantities.
- **Stock Movements:** recorded main/B2B transfer sides, B2B sales and sale returns.
- **Dashboard / Reports:** net B2B sales, outstanding balances, current B2B inventory cost, cost of goods and gross profit.
- **Reports -> Export CSV:** available when the user has report and export permissions.

Date filters apply to the selected invoices. Returns and subsequent payments update those invoices' net values; this is not a separate cash-flow report for the dates on which every return/payment occurred. Current stock value means current stock, regardless of the invoice date filter.

Users see permitted warehouses. Separate permissions control B2B product/customer management, transfers, POS access, sales, credit sales, payments, returns and reports. Your existing company Admin can assign these in the role editor.

This implementation follows your manual-price request. Automatic wholesale prices, customer price lists, quantity-price tiers, MOQ/carton enforcement, direct B2B purchasing/opening stock and B2B stock count adjustments are not part of this delivered flow. Allocate stock from main through documented transfers.

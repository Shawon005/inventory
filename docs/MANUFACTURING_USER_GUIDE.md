# Using Manufacturing

Manufacturing records how your business turns materials into products. It tracks what was taken from the warehouse, what usable products came back, what was wasted, and what each finished unit cost.

The normal workflow is:

**Create products -> receive materials -> create a BOM -> plan production -> start a batch -> receive output -> sell the finished stock.**

## Product types

| Type | Meaning | Example |
| --- | --- | --- |
| Normal product | An ordinary product outside your manufacturing recipes | A bought-and-resold accessory |
| Raw material | An input used in production | Plastic granules |
| Manufactured component | A part that can be made and later used in another product | A bulb body |
| Manufactured finished product | The completed product you intend to sell | An LED bulb |

Create these through the existing Products > Add Product form, or Add product on the manufacturing lists. In the Manufacturing & production section choose the classification. Stock tracking must be enabled. Manufactured products support single or variable product structures; a separate recipe targets each output variation. The existing single/variable/combo selector describes the product's structure, not its manufacturing classification.

Procurement controls whether the classified product may be purchased externally. Choose External purchases allowed when you buy it from suppliers; choose Manufacture internally only when it should be produced inside your business. This setting does not start production.

Saving a product, selecting its classification, or assigning a recipe creates no stock. Use a received purchase, an appropriate opening stock entry, or an actual production receipt to create inventory. Existing Products remains the product master; Manufacturing does not create a second copy of the same product.

## What each page does

| Page | Use |
| --- | --- |
| Overview | See planned, running and completed orders, recent batches and work in progress value |
| Raw materials | Find materials, warehouse quantities, reorder levels, procurement settings and product details |
| Components | Find manufactured components; select Manufactured finished product in the Product type filter to see finished goods |
| BOMs | Define recipes and publish revised recipes |
| Production orders | Plan jobs, inspect required stock, issue inputs and receive output |
| History | Review production batches, accepted/defective quantities and actual costs |
| Stock movements | Trace recorded purchases of manufacturing inputs, material issues, production receipts, returns, adjustments and reversals |
| Reports | Compare estimated and actual production costs; export CSV when permitted |

Warehouse filters show only warehouses the current user may access. The new movement ledger records postings from installation onward; existing older transaction history remains in the existing history screens. Reports are production cost reports, not a complete profit or cash report.

## Worked example: 100 LED bulbs

The following prices and quantities are examples, not records added to your database.

First create the products: Plastic granules as a raw material, Bulb body as a component, and LED bulb as a finished product. Purchased circuits and caps can be components with external purchases allowed.

If you manufacture the bulb bodies, create a separate body recipe and produce the bodies first. Their production cost becomes their inventory cost. Alternatively receive purchased bodies when procurement allows it.

### 1. Receive the input stock

Use Receive purchase to open the existing purchase form. Select the supplier, warehouse, materials, quantities and prices. A purchase must be received to make its quantities available for production. An unreceived order or a saved product definition is not usable stock.

The inputs must be in the production warehouse. Stock in another warehouse must be transferred through the existing transfer workflow before it can be used here.

### 2. Create the recipe

Open BOMs > Create BOM. BOM means Bill of Materials: the recipe for making the output.

For the simplest starting point, set Manufactured output to LED bulb and Expected output per recipe to 1. Add:

| Input | Quantity for one bulb |
| --- | --- |
| Bulb body | 1 piece |
| LED circuit | 1 piece |
| Cap | 1 piece |

Enter estimated labor, machine, overhead and other costs for that recipe quantity. Publish the version. An order for 100 bulbs will scale a one-bulb recipe by 100. If you instead define a recipe for 100 bulbs, its inputs and estimated costs must also describe 100 bulbs.

Recipe units must belong to the input product's unit family. For example, a plastic recipe can use grams when grams are configured under the material's kilogram base unit. Expected waste adds a planning allowance to required material; actual waste is recorded when materials are issued.

A new recipe version preserves previous versions. Existing production orders keep the recipe snapshot selected when they were created; publishing a revision does not rewrite them.

### 3. Plan the job

Click Plan production on the recipe, or on the overview/orders page. Select the active BOM version, production warehouse and planned accepted quantity: 100. Add dates, operator and notes if useful. Save as Planned, or save Draft and later click Mark planned.

The requirements preview shows required quantities, available quantities and shortages. Planning does not deduct or reserve stock. Another sale or production order may use that stock before your batch starts, so availability is checked again at start.

If a required component has its own recipe, Plan component creates a separate related production order. It does not automatically manufacture or receive that component. Complete that order first, then start final assembly. The final assembly consumes component stock at its existing cost; it does not deduct the plastic again.

### 4. Start the batch

Open the order and click Start next batch. Set Planned batch output to 100, check Actual issue quantities and record any Actual waste within issue. Click Issue materials & start.

This immediately deducts the issued input quantities from the selected warehouse and records their cost as work in progress. It does not yet add finished bulbs.

Actual issue is the total taken out, including waste. For example, if 4.1 KG is taken and 0.1 KG is wasted, enter issue 4.1 and waste 0.1. The deduction is 4.1 KG. These fields use each product's inventory unit; do not enter grams into a KG field.

Only one batch may be open on the order at a time. You can choose a smaller batch than the remaining order quantity.

### 5. Receive finished production

On the running batch click Receive output. Enter accepted output, defective output, actual batch expenses, and optionally a lot number and expiry date. Confirm Receive stock.

For a batch target of 100, accepted plus defective must equal 100. If accepted is 98 and defective is 2, only 98 bulbs enter sellable stock. The order remains partially completed because its accepted target is 100. A subsequent batch can make the remaining 2 accepted bulbs.

One receipt closes one batch. For production over several days, start and receive smaller sequential batches rather than receiving one batch repeatedly.

Actual expense fields are totals for this batch, not costs per bulb. Estimated BOM expenses do not automatically fill actual expenses. Recording a production expense does not itself pay wages, suppliers or cash.

## How cost is calculated

Suppose the 100 accepted bulbs use the following inputs:

| Cost | Example amount |
| --- | ---: |
| 100 bodies at 20 | 2,000 |
| 100 circuits at 35 | 3,500 |
| 100 caps at 5 | 500 |
| Actual labor | 500 |
| Actual machine cost | 200 |
| Actual overhead | 300 |
| Total batch cost | 7,000 |

Cost per accepted bulb = 7,000 / 100 = 70. If the same batch instead has 98 accepted bulbs and 2 defective, cost per accepted bulb is 7,000 / 98 = 71.4286. Defective products do not create stock, so their cost is absorbed by the accepted output in that batch.

Material costs come from the actual inventory cost layers selected under the business's FIFO/LIFO method, not from the sales price. Returned unused inputs reduce the material cost. A finished component already carries its own material and production costs into the next assembly.

Manufacturing calculates inventory cost. Set the selling price separately through the existing product/pricing workflow.

## What changes stock

| Action | Material/input stock | Finished output stock |
| --- | --- | --- |
| Create/edit product or BOM | No change | No change |
| Draft/plan an order | No change | No change |
| Receive a supplier purchase | Purchased item increases | Purchased item increases if applicable |
| Start a production batch | Issued quantities decrease | No change |
| Return unused inputs while batch is open | Returned quantities increase | No change |
| Receive production | No second deduction | Accepted quantity increases |
| Sell finished products through existing sales/POS | No second raw-material deduction | Sold quantity decreases |
| Reverse production when permitted | Restores outstanding issued quantities | Removes received quantity |

## Order statuses

| Status | Meaning |
| --- | --- |
| Draft | Saved plan, not ready to start |
| Planned | Ready for a batch to start |
| In progress | A batch has issued materials and is open |
| Partially completed | Some accepted output received; target not reached; no open batch |
| Completed | Accepted output has reached the order target |
| Cancelled | Order stopped; any posted batches had to be reversed first |

## Unused material, failed batches and corrections

- Return unused: while the batch is open, return usable material to its original warehouse and cost layer. Record a reason. Material already recorded as waste cannot be returned as unused.
- Scrap batch: use when the entire run fails and there is zero accepted output. It closes the batch without output stock and records the batch cost as a write-off. It does not mean the consumed material is returned to stock.
- Reverse: correct a mistaken posting through linked reversal records. If the output has already been sold, transferred, adjusted, returned or used in another production run, those dependent transactions must be reversed before this production can be reversed.
- Cancel order: stops an unposted order, or an order whose batches have all been reversed. Cancellation alone does not undo posted production.
- Stock adjustment: records a genuine count correction with a reason. Positive adjustments require a unit cost. Use production receipts for manufactured output and purchases for supplier receipts.
- Archive: marks a product or recipe inactive while preserving its history and balances. It is not a stock write-off.

## Finding answers later

Open a product's Manufacturing details to see warehouse stock, production history, component usage and purchase history. Use History to inspect batches, Stock movements to trace quantity/cost postings, and Reports to compare estimated and actual order costs.

If an output is missing from recipe search, check that it is active, stock-tracked and classified as a component or manufactured finished product. If stock is unavailable, check the warehouse, purchase receipt status, usable cost layers and expiry. If a button is missing, check the user's manufacturing abilities and warehouse access through the existing role/user settings.

A practical division of work is: purchasing staff receive inputs, a production planner creates recipes/orders, an operator issues materials, a supervisor receives output and enters actual costs, and an inventory manager handles adjustments/reversals. Your Admin can assign the permissions to match your team.

The B2B workspace allocates selected quantities from main stock and uses manually entered sale prices. See [B2B_USER_GUIDE.md](B2B_USER_GUIDE.md). Received manufactured stock can also be sold through the existing permitted main sales/POS workflow.

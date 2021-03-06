<?php namespace App\DTIStore\Repositories;

use App\DTIStore\Helpers\StatusHelper;
use App\StoreStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreStockRepository implements StoreStockInterface
{

    public function create(array $data)
    {
        $branchStock = StoreStock::create($data);

        return $branchStock;
    }

    public function find($id)
    {
        $branchStock = StoreStock::find($id);

        return $branchStock;
    }

    public function findByBranchVariationId($branchId, $variationId)
    {
        $branchStock = StoreStock::where('store_id', $branchId)
            ->where('product_variation_id', $variationId)
            ->first();

        return $branchStock;
    }

    public function getAll()
    {
        $branchStocks = StoreStock::all();

        return $branchStocks;
    }

    public function filter(array $filter)
    {
        // TODO: filters
        $branchStocks = $this->getAll();

        return $branchStocks;
    }

    public function update($id, $data)
    {
        $branchStock = $this->find($id);

        if (!$branchStock) {
            return false;
        }

        $updated = $branchStock->update($data);

        return $updated;
    }

    public function delete($id)
    {
        $branchStock = $this->find($id);

        if (!$branchStock) {
            return false;
        }

        if ($branchStock->status == StatusHelper::DELETED) {
            return true;
        }

        $deleted = $branchStock->update([
            'deleted_at' => Carbon::now()->toDateTimeString(),
            'status' => StatusHelper::DELETED,
        ]);

        return $deleted;
    }

    public function destroy($id)
    {
        $branchStock = $this->find($id);

        if (!$branchStock) {
            return false;
        }

        $destroyed = $branchStock->delete();

        return $destroyed;
    }

    public function isDeleted($id)
    {
        $branchStock = $this->find($id);

        if (!$branchStock) {
            return true;
        }

        if ($branchStock->status != StatusHelper::DELETED) {
            return false;
        }

        return true;
    }

    public function addStocksByBranchVariationId($branchId, $variationId, $quantity)
    {
        return DB::transaction(function () use ($quantity, $variationId, $branchId) {

            $branchStock = $this->findByBranchVariationId($branchId, $variationId);

            if (!$branchStock) {
                $branchStock = $this->create([
                    'store_id' => $branchId,
                    'product_variation_id' => $variationId,
                    'quantity' => $quantity
                ]);

                return true;
            }

            $updated = $branchStock->update([
                'quantity' => $branchStock->quantity + $quantity
            ]);

            return $updated;
        });

    }

    public function subtractStocksByBranchVariationId($branchId, $variationId, $quantity)
    {
        return DB::transaction(function () use ($quantity, $variationId, $branchId) {
            $branchStock = $this->findByBranchVariationId($branchId, $variationId);

            if (!$branchStock) {
                $branchStock = $this->create([
                    'store_id' => $branchId,
                    'product_variation_id' => $variationId,
                    'quantity' => $quantity
                ]);

                return true;
            }

            $updated = $branchStock->update([
                'quantity' => $branchStock->quantity - $quantity
            ]);

            return $updated;
        });

    }

    public function getBranchStocksByItemIds($branchId, array $itemIds)
    {
        $branchStocks = StoreStock::where('store_id', $branchId)
            ->whereIn('product_variation_id', $itemIds)
            ->get();

        return $branchStocks;

    }

    public function getBranchStocksById($branchId, array $filter = [])
    {
        $activeFlag = StatusHelper::ACTIVE;
        $confirmedFlag = StatusHelper::CONFIRMED;

        // TODO: critical_for_company_multi_user
        // TODO: When company is present this will break

        /*
         * ,
                CASE
                WHEN branch_stocks.last_delivery_quantity_temp IS NULL
                THEN branch_stocks.branch_quantity
                ELSE branch_stocks.last_delivery_quantity_temp + branch_stocks.branch_quantity
                END AS branch_total_delivery_quantity,
         */

        $querySql = "";

        if(isset($filter['q'])){
            $queryString = $filter['q'];
            $querySql = " AND LOWER(branch_stocks.product_name) LIKE LOWER('%{$queryString}%') ";
        }

        $additionalFilters = $this->getAdditionalFilters($filter);

        $transactionDateFilter = $this->getTransactionDateFilter($filter);

        $sql = "SELECT 
                branch_stocks_query.id,
                branch_stocks_query.product_name,
                branch_stocks_query.store_id,
                branch_stocks_query.name,
                branch_stocks_query.code,
                branch_stocks_query.image_url,
                branch_stocks_query.product_category_id,
                branch_stocks_query.category,
                branch_stocks_query.category_code,
                branch_stocks_query.cost_price,
                branch_stocks_query.selling_price,
                branch_stocks_query.metrics,
                branch_stocks_query.size,
                branch_stocks_query.status,
                branch_stocks_query.product_id,
                branch_stocks_query.branch_quantity,
                branch_stocks_query.company_quantity,
                branch_stocks_query.current_delivery_quantity,
                branch_stocks_query.sale_item_count,
                branch_stocks_query.return_sale_item_count,
                branch_stocks_query.last_delivery_quantity_temp,
                branch_stocks_query.branch_total_delivery_quantity,
                (branch_stocks_query.sale_item_count - branch_stocks_query.return_sale_item_count) as sold_items,
                CASE 
                WHEN branch_stocks_query.last_delivery_quantity_temp IS NULL 
                THEN 0 
                WHEN branch_stocks_query.branch_quantity > branch_stocks_query.last_delivery_quantity_temp
                THEN branch_stocks_query.branch_quantity
                ELSE branch_stocks_query.last_delivery_quantity_temp 
                END AS last_delivery_quantity_old,
                branch_stocks_query.current_delivery_quantity as last_delivery_quantity,
                CASE 
                WHEN branch_stocks_query.last_delivery_quantity_temp IS NULL 
                THEN 100
                WHEN  ROUND((branch_stocks_query.branch_quantity/branch_stocks_query.current_delivery_quantity)*100, 2) > 100
                THEN 100
                ELSE ROUND((branch_stocks_query.branch_quantity/branch_stocks_query.current_delivery_quantity)*100, 2)
                END AS branch_delivery_percentage
                FROM (SELECT 
                  CONCAT(products.name,' (',ROUND(product_variations.size),' ',product_variations.metrics,') ',products.code) as product_name,
                  product_variations.`id`,
                  products.`name`,
                  products.`code`,
                  products.`image_url`,
                  products.`product_category_id`,
                  product_categories.`name` AS category,
                  product_categories.`code` AS category_code,
                  product_variations.`cost_price`,
                  product_variations.`selling_price`,
                  product_variations.`metrics`,
                  product_variations.`size`,
                  product_variations.`status`,
                  product_variations.`product_id`,
                  branch_stocks.`store_id`,
                  branch_stocks.`created_at`,
                  branch_stocks.`quantity` AS branch_quantity,
                  company_stocks.`quantity` AS company_quantity,
                  branch_stocks.current_delivery_quantity,
                    
                  (SELECT 
                    CASE WHEN SUM(transaction_items.`quantity`) IS NULL THEN 0 ELSE SUM(transaction_items.`quantity`) END
                  FROM
                    transaction_items 
                    INNER JOIN transactions 
                      ON transactions.id = transaction_items.`transaction_id` 
                    INNER JOIN transaction_types 
                      ON transaction_types.id = transactions.`transaction_type_id` 
                  WHERE transactions.status = 'active' 
                    AND transaction_types.`code` = 'sale' 
                    AND transactions.`store_id` = branch_stocks.`store_id` 
                    AND transaction_items.`product_variation_id` = branch_stocks.`product_variation_id` 
                    {$transactionDateFilter}) AS sale_item_count,
                    (SELECT 
                        CASE WHEN SUM(transaction_items.`quantity`) IS NULL THEN 0 ELSE SUM(transaction_items.`quantity`) END
                      FROM
                        transaction_items 
                        INNER JOIN transactions 
                          ON transactions.id = transaction_items.`transaction_id` 
                        INNER JOIN transaction_types 
                          ON transaction_types.id = transactions.`transaction_type_id` 
                      WHERE transactions.status = 'active' 
                        AND transaction_types.`code` = 'return_sale' 
                        AND transactions.`store_id` = branch_stocks.`store_id` 
                        AND transaction_items.`product_variation_id` = branch_stocks.`product_variation_id`) AS return_sale_item_count,
                  
                  (SELECT delivery_items.`quantity` 
                  FROM delivery_items 
                  INNER JOIN deliveries d ON d.id = delivery_items.delivery_id
                  WHERE delivery_items.product_variation_id = branch_stocks.`product_variation_id` 
                  AND d.status = '{$confirmedFlag}'
                  ORDER BY delivery_items.`id` DESC 
                  LIMIT 1) AS last_delivery_quantity_temp,
                  
                  (SELECT 
                    SUM(delivery_items.`quantity`)
                  FROM
                    delivery_items 
                  WHERE delivery_items.`product_variation_id` = product_variations.`id`) AS branch_total_delivery_quantity
                  
                FROM
                  branch_stocks 
                  INNER JOIN product_variations 
                    ON product_variations.`id` = branch_stocks.`product_variation_id` 
                  INNER JOIN products 
                    ON products.`id` = product_variations.`product_id` 
                  INNER JOIN product_categories 
                    ON product_categories.`id` = products.`product_category_id` 
                  INNER JOIN company_stocks 
                    ON company_stocks.`product_variation_id` = branch_stocks.`product_variation_id` 
                WHERE store_id = {$branchId} 
                  AND product_variations.status = '{$activeFlag}') as branch_stocks_query
                   
                  WHERE branch_stocks_query.store_id IS NOT NULL 
                  {$additionalFilters}
                  {$querySql}";
//        dd($sql);
        $branchStocks = DB::select($sql);

        return $branchStocks;
    }

    public function getAdditionalFilters($filters)
    {
        $sortFilter = "";
        $orderFilter = "DESC";
        $branchIdFilter = "";

        if(isset($filters['store_id']) && $filters['store_id'] != 0) {
            $branchId = $filters['store_id'];
            $branchIdFilter = "AND branch_stocks_query.store_id = {$branchId}";
        }
        if(isset($filters['order'])) {
            $orderFilter = $filters['order'];
        }

        if(isset($filters['sort'])) {
            $sort = $filters['sort'];

            switch ($sort) {
                case 'total_sold_items':
                    $sortFilter = "ORDER BY sold_items ".$orderFilter;
                    break;

                case 'product_name':
                    $sortFilter = "ORDER BY product_name ".$orderFilter;
                    break;

                case 'current_inventory':
                    $sortFilter = "ORDER BY branch_quantity ".$orderFilter;
                    break;
            }
        }

        $queryFilters = $branchIdFilter . " ". $sortFilter ;

        return $queryFilters;
    }

    private function getTransactionDateFilter($filters) {

        $columnPrefix = 'transactions';
        $fromSql = '';
        $toSql = '';

        if(isset($filters['from'])) {
            $from = $filters['from'];
            $fromSql = " AND DATE({$columnPrefix}.`created_at`) >= DATE('{$from}')";
        }

        if(isset($filters['to'])) {
            $to = $filters['to'];
            $toSql = " AND DATE({$columnPrefix}.`created_at`) <= DATE('{$to}') ";
        }

        $filters = $fromSql . $toSql;

//        dd($filters);
        return $filters;
    }
}
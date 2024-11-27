<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // DB::statement("
        //     CREATE OR REPLACE VIEW link_site_with_prices AS
        //     SELECT 
        //         ls.id AS link_site_id,
        //         MIN(ss.price_guest_post) AS lowest_price,
        //         (SELECT MIN(ss2.price_guest_post) 
        //          FROM seller_sites ss2 
        //          WHERE ss2.link_site_id = ls.id 
        //          AND ss2.price_guest_post > (SELECT MIN(ss3.price_guest_post) 
        //                                      FROM seller_sites ss3 
        //                                      WHERE ss3.link_site_id = ls.id)) AS fourth_lowest_price,
        //         (CASE 
        //             WHEN MIN(ss.price_guest_post) = 0 THEN 0 
        //             ELSE 
        //                 ((SELECT MIN(ss2.price_guest_post) 
        //                   FROM seller_sites ss2 
        //                   WHERE ss2.link_site_id = ls.id 
        //                   AND ss2.price_guest_post > (SELECT MIN(ss3.price_guest_post) 
        //                                               FROM seller_sites ss3 
        //                                               WHERE ss3.link_site_id = ls.id)) - MIN(ss.price_guest_post)
        //                 ) / MIN(ss.price_guest_post)
        //             END
        //         ) AS price_difference_percentage
        //     FROM 
        //         link_sites ls
        //     JOIN 
        //         seller_sites ss ON ls.id = ss.link_site_id
        //     GROUP BY 
        //         ls.id
        // ");

        DB::statement("
            CREATE OR REPLACE VIEW link_site_with_prices AS
            WITH price_data AS (
                SELECT 
                    ls.id AS link_site_id,
                    MIN(ss.price_guest_post) AS lowest_price,
                    (SELECT ss2.price_guest_post 
                    FROM seller_sites ss2 
                    WHERE ss2.link_site_id = ls.id
                    ORDER BY ss2.price_guest_post ASC 
                    LIMIT 1 OFFSET 3) AS fourth_lowest_price
                FROM 
                    link_sites ls
                JOIN 
                    seller_sites ss ON ls.id = ss.link_site_id
                GROUP BY 
                    ls.id
            )
            SELECT 
                link_site_id,
                lowest_price,
                fourth_lowest_price,
                (fourth_lowest_price - lowest_price) AS price_diff_abs,
                ((fourth_lowest_price - lowest_price) / lowest_price) * 100 AS price_diff_perc,
                ((fourth_lowest_price - lowest_price) / lowest_price) * 100 * LOG(1 + (fourth_lowest_price - lowest_price)) AS gap_score
            FROM 
                price_data;
    ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS link_site_with_prices");
    }
};

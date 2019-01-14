SELECT DISTINCT
    p0_.name AS name_0,
    p0_.price AS price_1,
    p0_.weight AS weight_2,
    p0_.created_at AS created_at_3,
    p0_.deleted_at AS deleted_at_4,
    p0_.calories_value AS calories_value_5,
    p0_.calories_percent AS calories_percent_6,
    p0_.fat_value AS fat_value_7,
    p0_.fat_percent AS fat_percent_8,
    p0_.saturated_fat_value AS saturated_fat_value_9,
    p0_.saturated_fat_percent AS saturated_fat_percent_10,
    p0_.sugar_value AS sugar_value_11,
    p0_.sugar_percent AS sugar_percent_12,
    p0_.salt_value AS salt_value_13,
    p0_.salt_percent AS salt_percent_14,
    p0_.serving_size_value AS serving_size_value_15,
    p0_.id AS id_16,
    p0_.category_id AS category_id_17,
    p0_.calories_unit_id AS calories_unit_id_18,
    p0_.fat_unit_id AS fat_unit_id_19,
    p0_.saturated_fat_unit_id AS saturated_fat_unit_id_20,
    p0_.sugar_unit_id AS sugar_unit_id_21,
    p0_.salt_unit_id AS salt_unit_id_22,
    p0_.serving_size_unit_id AS serving_size_unit_id_23,
    c1_.name AS category_name_24,
    i4_.location AS location_25
FROM
    product p0_
INNER JOIN
    category c1_
ON
    p0_.category_id = c1_.id
INNER JOIN
    product_image p2_
ON
    p0_.id = p2_.product_id AND p2_.type_id AND p2_.store_id IS NULL
INNER JOIN
    image i3_
ON
    p2_.image_id = i3_.id
INNER JOIN
    image_file i4_
ON
    i3_.id = i4_.image_id AND i4_.format_id = 1
WHERE
    c1_.id IN(
    SELECT
        c2_.id
    FROM
        category c2_
    WHERE
        (
            c2_.tree_right < 224 AND c2_.tree_left > 197
        ) OR c2_.id = :cid
    ORDER BY
        c2_.tree_left ASC
)
AND p0_.sugar_value IS NOT NULL

ORDER BY
    p0_.name ASC
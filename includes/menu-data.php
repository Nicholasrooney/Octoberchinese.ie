<?php
/*
 * October Chinese Dining: menu items and prices.
 *
 * Transcribed from the printed à la carte and lunch menus. Allergen codes are
 * copied exactly as printed — do not add or guess codes here, check the
 * kitchen first.
 *
 * Fields per dish:
 *   name, cn        English and Chinese names
 *   price           euro amount as a string, e.g. '18.95'
 *   price_label     optional label in front of the price, e.g. '¼'
 *   more_prices     optional extra sizes: [['½', '27.50']]
 *   allergens       codes from $allergenKey, e.g. ['1W', '3', '9']
 *   desc            one line under the name
 *   extra           further lines (choices, notes)
 *   veg / seafood / chef   true to show the matching icon
 *   spice           0–5 chillies, as printed on the menu
 */

$allergenKey = [
    '1W' => 'Gluten (wheat)',
    '1B' => 'Gluten (barley)',
    '2'  => 'Milk',
    '3'  => 'Eggs',
    '4'  => 'Fish',
    '5'  => 'Crustaceans',
    '6'  => 'Molluscs',
    '7'  => 'Peanuts',
    '8'  => 'Nuts (cashews)',
    '9'  => 'Sesame seeds',
    '10' => 'Sulphur dioxide & sulphites',
    '11' => 'Soya beans',
    '12' => 'Celery',
    '13' => 'Mustard',
    '14' => 'Lupin',
];

/** Small helper so each dish below reads as one line of data. */
if (!function_exists('dish')) {
function dish(string $name, string $cn, string $price, array $allergens, string $desc = '', array $opts = []): array
{
    return array_merge([
        'name' => $name, 'cn' => $cn, 'price' => $price, 'allergens' => $allergens,
        'desc' => $desc, 'extra' => [], 'veg' => false, 'seafood' => false,
        'chef' => false, 'spice' => 0, 'price_label' => '', 'more_prices' => [],
    ], $opts);
}
}

$menu = [
    [
        'id'    => 'october-special',
        'title' => 'October Specials',
        'cn'    => '食悦特色',
        'note'  => '',
        'intro' => 'Our special Chinese dishes, thoughtfully prepared for sharing with family and friends.',
        'items' => [
            dish('Smashed Cucumber Salad', '拍黄瓜拌木耳', '8.00', ['4', '5', '7', '9', '11'],
                'With black fungus, carrot and coriander in our house salad dressing.', ['veg' => true, 'spice' => 1]),
            dish('Century Eggs with Green Chillies', '椒香皮蛋', '9.50', ['1W', '3', '4', '5', '11'],
                '', ['veg' => true, 'spice' => 1]),
            dish('Hunan Chilli Beef Salad', '湘味凉拌牛肉', '12.50', ['5', '9', '11'],
                'Sliced beef with cucumber in our house spicy dressing.', ['spice' => 2]),
            dish('Shredded Chicken Salad', '凉拌手撕鸡', '11.50', ['5', '7', '9', '11'],
                'Shredded chicken thigh with crushed peanut in our house spicy dressing.', ['spice' => 2]),
            dish('Stir-fried Chicken Thigh in Claypot', '飘香三杯鸡', '18.50', ['1W', '2', '5', '7', '9', '11'],
                'With basil and soy sauce.'),
            dish('Sichuan Spicy Chicken', '四川辣子鸡', '18.50', ['3', '9', '11'],
                'With dried chillies.', ['spice' => 2]),
            dish('Braised Beef Fillet or Sea Bass & Vegetables', '水煮牛柳 / 水煮鱼', '22.50', ['1W', '4', '9', '11'],
                'In hot Sichuan sauce.', ['chef' => true, 'spice' => 4]),
            dish('Stir-fried Beef Fillet', '野山椒小炒牛', '22.50', ['1W', '9', '11', '12'],
                'With wild chilli pepper.', ['spice' => 3]),
            dish('Steamed Sea Bass', '清蒸鲈鱼', '20.50', ['4', '9', '11'],
                'With ginger, scallion and soy sauce.', ['seafood' => true]),
            dish('Steamed Sea Bass with Hunan Chopped Chillies', '剁椒蒸鲈鱼', '22.50', ['4', '9', '11'],
                'With chopped chillies.', ['chef' => true, 'spice' => 3]),
            dish('Sizzling Seafood Pot', '干锅海鲜', '22.00', ['4', '9', '11'],
                'With chopped chillies.', ['seafood' => true, 'spice' => 4]),
            dish('Dry-fried Green Beans', '干煸豆角', '13.95', ['9', '11'],
                '', ['veg' => true, 'spice' => 1]),
            dish('Stir-fried Aubergine & Green Beans', '茄子烧豆角', '14.95', ['9', '11'],
                '', ['veg' => true]),
            dish('Stir-fried Cabbage with Dried Chillies', '手撕包菜', '11.95', ['9', '11'],
                '', ['veg' => true, 'spice' => 2]),
        ],
    ],
    [
        'id'    => 'appetisers',
        'title' => 'Appetisers',
        'cn'    => '头盘',
        'note'  => '',
        'intro' => '',
        'items' => [
            dish('Bao Buns with Crispy Duck', '香酥荷叶包', '9.50', ['1W', '9', '10', '11'],
                'Two soft steamed buns filled with crispy duck, shredded cucumber, scallions and hoisin sauce.'),
            dish('Vegetable Spring Rolls', '素春卷', '8.50', ['1W', '3', '4', '5', '11'],
                'Two crispy vegetable spring rolls with sweet chilli sauce.', ['veg' => true]),
            dish('Kataifi Wrapped King Prawns', '芙蓉虾卷', '12.95', ['1W', '2', '4', '5', '9'],
                'Two fluffy kataifi-wrapped prawns with mango lime sauce.', ['seafood' => true]),
            dish('Sesame Prawn on Toast', '芝麻虾多士', '11.50', ['1W', '5', '9'],
                'Two smashed prawn toasties with sesame seeds.', ['seafood' => true]),
            dish('Traditional Crispy King Prawns', '椒盐虾', '12.50', ['1W', '3', '5', '9'],
                'Deep-fried king prawns with salt and chilli.', ['seafood' => true]),
            dish('Traditional Crispy Squid', '椒盐鱿鱼', '11.50', ['1W', '3', '6', '9'],
                'Deep-fried squid with seasoning.', ['seafood' => true]),
            dish('Cantonese-style Pork Ribs', '香江排骨', '12.50', ['1W', '10', '11'],
                'Pork ribs with BBQ, honey, or salt and chilli.'),
            dish('Crispy Aromatic Duck', '香酥鸭', '14.50', ['1W', '10', '11'],
                'With steamed pancakes, shredded cucumber, scallions and hoisin sauce.',
                ['price_label' => '¼', 'more_prices' => [['½', '27.50']]]),
            dish('Crispy Fried Mixed Mushrooms', '椒香杂菌', '11.90', ['1W', '3'],
                'Deep-fried mixed mushrooms with sweet chilli or five spice.', ['veg' => true]),
        ],
    ],
    [
        'id'    => 'soup',
        'title' => 'Soup',
        'cn'    => '汤',
        'note'  => '',
        'intro' => '',
        'items' => [
            dish('Chicken & Sweetcorn Soup', '鸡茸玉米汤', '8.00', ['1W', '3', '5', '9', '11'],
                'Shredded chicken, cream-style sweetcorn and egg in chicken broth.'),
            dish('Hot & Sour Soup', '酸辣汤', '8.00', ['1W', '3', '5', '9', '11'],
                'Shredded chicken, vegetables, black fungus, tofu and egg in a tangy, spicy soup.'),
            dish('Wonton Soup', '鲜虾云吞汤', '9.50', ['1W', '3', '5', '9', '11'],
                'Minced prawn and pork dumplings in chicken broth with vegetables.'),
        ],
    ],
    [
        'id'    => 'mains',
        'title' => 'Main Dishes',
        'cn'    => '主菜',
        'note'  => '',
        'intro' => '',
        'items' => [
            dish('Stir-fried Chicken Fillet', '炒鸡柳', '18.95', ['1W', '2', '5', '7', '8', '9', '11'],
                'Choice of ginger & scallions, curry, kung po or cashew nuts.'),
            dish('Crispy Shredded Chicken Fillet', '香脆鸡柳', '18.95', ['1W', '2', '3', '4', '9', '11'],
                'Choice of salt & chilli or honey sesame.'),
            dish('Sweet & Sour Chicken', '菠萝咕咾鸡', '18.95', ['1W', '2', '5', '7', '9', '11'],
                'Crispy fried chicken thigh, mixed peppers and pineapple.'),
            dish('Cantonese Roast Duck', '港式烧鸭', '24.50', ['1W', '10', '11'],
                'Choice of orange, plum or soy sauce.', ['price_label' => '½']),
            dish('Sizzling Beef Fillet', '铁板牛柳', '24.50', ['1W', '3', '9', '11'],
                'Choice of black pepper, black bean, or ginger & scallions.'),
            dish('Slow-cooked Beef Short Ribs in Claypot', '陈香慢炖牛肋骨', '27.95', ['1W', '5', '6', '11'],
                'With Shaoxing wine and aged tangerine peel.', ['chef' => true]),
            dish('Stir-fried King Prawns', '爆炒虾球', '22.50', ['1W', '5', '6', '8', '11'],
                'Choice of ginger & scallions, kung po or cashew nuts.', ['seafood' => true]),
            dish('Sizzling Sea Bass', '铁板招牌美味酱爆鲈鱼', '24.50', ['1W', '4', '8', '9', '11'],
                'Sea bass with asparagus in our house signature sauce.', ['seafood' => true]),
            dish('Surf & Turf', '避风塘虾和排骨', '23.50', ['1W', '4', '8', '9', '11'],
                'Salt and chilli king prawns and pork ribs in crispy breadcrumbs.', ['seafood' => true]),
            dish('Tofu & Seasonal Vegetables in Claypot', '豆腐煲', '16.95', ['1W', '2', '9'],
                'Choice of ginger & scallions or black bean.', ['veg' => true]),
            dish('Crispy Fried Aubergines in Sweet Soy & Pepper Sauce', '风味茄子', '15.50', ['1W', '9', '11'],
                'With coriander.', ['veg' => true]),
        ],
    ],
    [
        'id'    => 'dumplings',
        'title' => 'Dumplings',
        'cn'    => '饺子',
        'note'  => '4 pieces',
        'intro' => '',
        'items' => [
            dish('Pan-fried Prawn & Chive Dumplings', '香煎鲜虾韭菜饺', '8.95', ['1W', '3', '5', '9', '11']),
            dish('Pan-fried Chicken & Vegetable Dumplings', '香煎鸡肉蔬菜饺', '8.95', ['1W', '3', '9', '11']),
            dish('Pan-fried Mixed Vegetable Dumplings', '香煎什锦蔬菜饺', '8.95', ['1W', '3', '9', '11'], '', ['veg' => true]),
            dish('Steamed Beef & Vegetable Dumplings', '清蒸牛肉蔬菜饺', '8.95', ['1W', '3', '9', '11']),
            dish('Shanghai Soup Dumplings', '小笼包', '8.95', ['1W', '11']),
        ],
    ],
    [
        'id'    => 'noodles-rice',
        'title' => 'Noodles & Rice',
        'cn'    => '面和饭',
        'note'  => '',
        'intro' => '',
        'items' => [
            dish('Beef Fried Noodles or Rice', '牛肉炒面 / 饭', '16.95', ['1W', '3', '9', '11'],
                'With scallion, bean sprouts, carrot and peppers.'),
            dish('Chicken Fried Noodles or Rice', '鸡肉炒面 / 饭', '16.95', ['1W', '3', '9', '11'],
                'With scallion, bean sprouts, carrot and peppers.'),
            dish('King Prawn Fried Noodles or Rice', '鲜虾炒面 / 饭', '17.95', ['1W', '3', '4', '9', '11']),
            dish('Mixed Vegetable Fried Noodles or Rice', '时蔬炒面 / 饭', '14.95', ['1W', '9', '11'], '', ['veg' => true]),
        ],
    ],
    [
        'id'    => 'sides',
        'title' => 'Side Dishes',
        'cn'    => '副餐',
        'note'  => '',
        'intro' => '',
        'items' => [
            dish('Steamed Jasmine Rice', '白饭', '3.95', ['1W', '3', '5', '9', '11'], '', ['veg' => true]),
            dish('Egg Fried Rice', '蛋炒饭', '4.95', ['1W', '3', '11'], '', ['veg' => true]),
            dish('Egg White Fried Rice', '蛋白炒饭', '6.95', ['1W', '3', '11'], '', ['veg' => true]),
            dish('Plain Wok-fried Noodles with Egg', '蛋炒面', '7.50', ['1W', '3', '9', '11'], '', ['veg' => true]),
            dish('Chips', '薯条', '4.00', [], '', ['veg' => true]),
            dish('Stir-fried Tenderstem Broccoli', '生炒西兰苗', '8.50', ['9', '11'], '', ['veg' => true]),
            dish('Stir-fried Mixed Vegetables', '蒜蓉炒时蔬', '8.00', ['9', '11'], '', ['veg' => true]),
        ],
    ],
];

/*
 * Lunch menu, 12:30 – 16:00. Kept apart from the à la carte so the menu page
 * can show it under its own tab.
 */
$lunchHours = '12:30 – 16:00';

$lunchMenu = [
    [
        'id'    => 'lunch-bowls',
        'title' => 'Signature Rice Bowls',
        'cn'    => '招牌盖饭',
        'note'  => '',
        'intro' => 'Steamed jasmine rice, fresh ingredients and our signature sauce. Every bowl comes with half a soy-braised egg. Add a soft drink for €1.50.',
        'items' => [
            dish('Ginger & Spring Onion Chicken', '', '13.95', ['1W', '2', '5', '7', '9', '11'],
                'Wok-tossed chicken with ginger, spring onions and a light savoury sauce.'),
            dish('Braised Beef Brisket & Potato', '', '14.95', ['1W', '2', '5', '7', '9', '11'],
                'Slow-braised beef brisket and potatoes in a rich Chinese-style tomato sauce.'),
            dish('Cantonese Roast Duck', '', '15.50', ['1W', '9', '10', '11'],
                'Roast duck, steamed greens and our signature roast duck sauce.'),
            dish('Sichuan Tofu', '', '13.50', ['1W', '3', '9', '11'],
                'Tofu with mushrooms and fresh vegetables in a gently spicy Sichuan-style sauce.', ['veg' => true]),
            dish('Garden Vegetable Rice Bowl', '', '12.95', ['3', '9', '11'],
                'Fresh seasonal vegetables wok-tossed in a light savoury sauce.', ['veg' => true]),
        ],
    ],
    [
        'id'    => 'lunch-noodle-soups',
        'title' => 'Noodle Soups',
        'cn'    => '汤面',
        'note'  => '',
        'intro' => 'Comforting Chinese noodle bowls. Add a soft drink for €1.50.',
        'items' => [
            dish('Taiwanese Braised Beef Noodle Soup', '', '14.95', ['1W', '3', '5', '9', '11'],
                'Slow-braised beef brisket, noodles, greens and half a soy-braised egg in aromatic beef broth.'),
            dish('Wonton Noodle Soup', '', '13.95', ['1W', '3', '5', '9', '11'],
                'Prawn wontons with noodles and fresh greens in a flavourful broth.', ['seafood' => true]),
        ],
    ],
    [
        'id'    => 'lunch-dumplings',
        'title' => 'Handmade Dumplings',
        'cn'    => '手工饺子',
        'note'  => '6 pieces',
        'intro' => 'Steamed, or served in hot & sour soup.',
        'items' => [
            dish('Beef Dumplings', '', '9.00', ['1W', '3', '9', '11'], 'Irish beef, onions and scallions.'),
            dish('Chicken Dumplings', '', '9.00', ['1W', '3', '9', '11'], 'Chicken, scallions and vegetables.'),
            dish('Prawn & Chive Dumplings', '', '9.00', ['1W', '3', '5', '9', '11']),
            dish('Mixed Vegetable Dumplings', '', '9.00', ['1W', '9', '11'], '', ['veg' => true]),
        ],
    ],
];

// Printed at the foot of the à la carte menu.
$serviceNote = 'Parties of 5 or more are subject to a 10% service charge, all of which goes directly to the staff working at the time.';

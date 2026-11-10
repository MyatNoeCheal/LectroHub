<?php
/* ══════════════════════════════════════════════════════════════
   LectroHub — Translation Engine
   Place in: /LectroHub/customer/lang/translate.php

   Usage:
     require_once __DIR__ . "/lang/translate.php";
     echo t('shop');                       // simple lookup
     echo t('showing_x_of_y', ['x' => 3, 'y' => 20]);  // with placeholders

   Language is read from $_SESSION['lang'], set by navbar.php's
   language switcher (?lang=en / ?lang=mm). Defaults to 'en'.

   Falls back to English automatically if a key is missing for the
   active language, and falls back to the raw key if missing from
   both — so a missing translation never breaks the page, it just
   shows in English (or the key name) until added below.
══════════════════════════════════════════════════════════════ */

$GLOBALS['LH_I18N'] = [

/* ══════════════════════════════════════════
   NAVBAR / FOOTER / SHARED CHROME
══════════════════════════════════════════ */
'site_title' => ['en' => 'LectroHub - Customer', 'mm' => 'LectroHub - ဖောက်သည်'],
'search_placeholder' => [
    'en' => 'Search products, brands…',
    'mm' => 'ကုန်ပစ္စည်း၊ အမှတ်တံဆိပ် ရှာဖွေပါ…',
],
'shop' => ['en' => 'Shop', 'mm' => 'စျေးဝယ်ရန်'],
'deals' => ['en' => 'Deals', 'mm' => 'အထူးလျှော့စျေး'],
'cart' => ['en' => 'Cart', 'mm' => 'ဈေး၀ယ်ခြင်း'],
'wishlist' => ['en' => 'Wishlist', 'mm' => 'အနှစ်သက်ဆုံးစာရင်း'],
'my_account' => ['en' => 'My Account', 'mm' => 'ကျွန်ုပ်၏အကောင့်'],
'my_orders' => ['en' => 'My Orders', 'mm' => 'ကျွန်ုပ်၏အော်ဒါများ'],
'sign_out' => ['en' => 'Sign Out', 'mm' => 'ထွက်ရန်'],
'sign_in' => ['en' => 'Sign In', 'mm' => 'ဝင်ရောက်ရန်'],
'create_account' => ['en' => 'Create Account', 'mm' => 'အကောင့်ဖွင့်ရန်'],
'hello_guest' => ['en' => 'Hello, Guest', 'mm' => 'ဧည့်သည် ကြိုဆိုပါတယ်'],
'guest_sub' => ['en' => 'Sign in for the full experience', 'mm' => 'အပြည့်အစုံအသုံးပြုရန် ဝင်ရောက်ပါ'],
'get_started' => ['en' => 'Get Started', 'mm' => 'စတင်လိုက်ပါ'],
'home' => ['en' => 'Home', 'mm' => 'ပင်မစာမျက်နှာ'],

'footer_tagline' => [
    'en' => 'Premium electronics for those who demand the best. Curated tech, delivered fast.',
    'mm' => 'အကောင်းဆုံးကို လိုချင်သူများအတွက် အဆင့်မြင့် အီလက်ထရွန်နစ်ပစ္စည်းများ။ ရွေးချယ်ထားသော နည်းပညာပစ္စည်းများကို လျင်မြန်စွာ ပို့ဆောင်ပေးပါသည်။',
],
'footer_shop' => ['en' => 'Shop', 'mm' => 'စျေးဝယ်ရန်'],
'footer_support' => ['en' => 'Support', 'mm' => 'အကူအညီ'],
'footer_company' => ['en' => 'Company', 'mm' => 'ကုမ္ပဏီ'],
'footer_faq' => ['en' => 'FAQ', 'mm' => 'မေးလေ့ရှိသောမေးခွန်းများ'],
'footer_track_order' => ['en' => 'Track Order', 'mm' => 'အော်ဒါခြေရာခံရန်'],
'footer_returns' => ['en' => 'Returns', 'mm' => 'ပြန်အမ်းမှုများ'],
'footer_warranty' => ['en' => 'Warranty', 'mm' => 'အာမခံ'],
'footer_contact' => ['en' => 'Contact Us', 'mm' => 'ဆက်သွယ်ရန်'],
'footer_about' => ['en' => 'About', 'mm' => 'အကြောင်း'],
'footer_careers' => ['en' => 'Careers', 'mm' => 'အလုပ်အကိုင်များ'],
'footer_press' => ['en' => 'Press', 'mm' => 'သတင်းစာပေ'],
'footer_privacy' => ['en' => 'Privacy', 'mm' => 'ကိုယ်ရေးကိုယ်တာမူဝါဒ'],
'footer_terms' => ['en' => 'Terms', 'mm' => 'စည်းမျဉ်းစည်းကမ်းများ'],
'footer_rights' => ['en' => 'All rights reserved.', 'mm' => 'မူပိုင်ခွင့်အားလုံး ရယူထားသည်။'],
'toast_done' => ['en' => 'Done', 'mm' => 'ပြီးပါပြီ'],

/* ══════════════════════════════════════════
   WISHLIST PAGE
══════════════════════════════════════════ */
'my_wishlist_title' => ['en' => 'My Wishlist', 'mm' => 'ကျွန်ုပ်၏အကြိုက်စာရင်း'],
'wishlist_sub' => ['en' => "Products you've saved for later", 'mm' => 'နောက်ပိုင်းအတွက် သိမ်းထားသောပစ္စည်းများ'],
'item_singular' => ['en' => 'item', 'mm' => 'ခု'],
'item_plural' => ['en' => 'items', 'mm' => 'ခု'],
'add_all_to_cart' => ['en' => 'Add All to Cart', 'mm' => 'အားလုံးကို ခြင်းတောင်းထဲထည့်မည်'],
'wishlist_empty_title' => ['en' => 'Your wishlist is empty', 'mm' => 'သင့်အကြိုက်စာရင်း ဗလာဖြစ်နေသည်'],
'wishlist_empty_sub' => [
    'en' => 'Save items you love by clicking the heart icon on any product.',
    'mm' => 'မည်သည့်ပစ္စည်းပေါ်ရှိ နှလုံးသားအိုင်ကွန်ကိုနှိပ်၍ သင်နှစ်သက်သောပစ္စည်းများကိုသိမ်းပါ။',
],
'browse_products' => ['en' => 'Browse Products', 'mm' => 'ကုန်ပစ္စည်းများကြည့်ရန်'],
'guest_wishlist_notice' => ["en" => "You're browsing as a guest.", 'mm' => 'သင်ဧည့်သည်အနေဖြင့် ကြည့်ရှုနေပါသည်။'],
'guest_wishlist_signin_link' => ['en' => 'Sign in', 'mm' => 'ဝင်ရောက်ပါ'],
'guest_wishlist_notice_suffix' => ['en' => 'to save your wishlist permanently.', 'mm' => 'သင့်အကြိုက်စာရင်းကို အမြဲသိမ်းဆည်းရန်။'],
'remove_from_wishlist_title' => ['en' => 'Remove from wishlist', 'mm' => 'အကြိုက်စာရင်းမှ ဖယ်ရှားရန်'],
'remove_from_wishlist_aria' => ['en' => 'Remove {name} from wishlist', 'mm' => '{name} ကို အကြိုက်စာရင်းမှ ဖယ်ရှားရန်'],
'out_of_stock' => ['en' => 'Out of Stock', 'mm' => 'ကုန်သွားပါပြီ'],
'in_stock' => ['en' => 'In Stock', 'mm' => 'ရရှိနိုင်ပါသည်'],
'only_x_left' => ['en' => 'Only {x} left', 'mm' => 'ကျန်သည်မှာ {x} ခုသာ'],
'clear_wishlist' => ['en' => 'Clear Wishlist', 'mm' => 'အကြိုက်စာရင်းရှင်းမည်'],


/* ══════════════════════════════════════════
   HOMEPAGE
══════════════════════════════════════════ */
'hero_eyebrow' => ['en' => 'New arrivals just dropped', 'mm' => 'ကုန်ပစ္စည်းအသစ်များ ရောက်ရှိပြီ'],
'hero_title_1' => ['en' => "Tomorrow's", 'mm' => 'အနာဂတ်'],
'hero_title_2' => ['en' => 'Tech.', 'mm' => 'နည်းပညာကို'],
'hero_title_3' => ['en' => "Today's Price.", 'mm' => 'ယနေ့ဈေးနှုန်းဖြင့် ဝယ်ယူပါ'],
'hero_sub' => [
    'en' => "Discover the latest in consumer electronics — from flagship smartphones to pro-grade audio gear, curated for enthusiasts.",
    'mm' => 'အဆင့်မြင့် စမတ်ဖုန်းများမှသည် ပရော်ဖက်ရှင်နယ် အသံစက်ပစ္စည်းများအထိ — စိတ်ဝင်စားသူများအတွက် အထူးရွေးချယ်ထားသော အီလက်ထရွန်နစ်ပစ္စည်းအသစ်များကို ရှာဖွေလိုက်ပါ။',
],
'explore_products' => ['en' => 'Explore Products', 'mm' => 'ကုန်ပစ္စည်းများ ကြည့်ရှုရန်'],
'view_deals' => ['en' => 'View Deals', 'mm' => 'အထူးလျှော့စျေးများ ကြည့်ရန်'],
'stat_products' => ['en' => 'Products', 'mm' => 'ကုန်ပစ္စည်းများ'],
'stat_rating' => ['en' => 'Rating', 'mm' => 'အဆင့်သတ်မှတ်ချက်'],
'stat_customers' => ['en' => 'Happy Customers', 'mm' => 'ကျေနပ်သောဖောက်သည်များ'],
'browse' => ['en' => 'Browse', 'mm' => 'ရှာဖွေကြည့်ရှုရန်'],
'shop_by_category' => ['en' => 'Shop by Category', 'mm' => 'အမျိုးအစားအလိုက် ဝယ်ယူရန်'],
'no_categories' => ['en' => 'No categories available.', 'mm' => 'အမျိုးအစားများ မရှိသေးပါ။'],
'items_suffix' => ['en' => 'items', 'mm' => 'ခု'],
'trending' => ['en' => 'Trending', 'mm' => 'ရေပန်းစားနေသော'],
'featured_products' => ['en' => 'Featured Products', 'mm' => 'အထူးဖော်ပြထားသော ကုန်ပစ္စည်းများ'],
'view_all' => ['en' => 'View All', 'mm' => 'အားလုံးကြည့်ရန်'],
'featured' => ['en' => 'Featured', 'mm' => 'အထူးဖော်ပြထားသော'],
'add_to_cart' => ['en' => 'Add to Cart', 'mm' => 'ဈေး၀ယ်ခြင်းထဲထည့်ရန်'],
'sold_suffix' => ['en' => 'sold', 'mm' => 'ခု ရောင်းပြီး'],
'no_featured' => [
    'en' => 'No featured products available. Mark products as Featured in the admin panel.',
    'mm' => 'အထူးဖော်ပြထားသော ကုန်ပစ္စည်းများ မရှိသေးပါ။',
],
'promo_today_only' => ['en' => 'Today Only', 'mm' => 'ယနေ့တစ်ရက်သာ'],
'promo_flash_title' => ['en' => 'Flash Sale', 'mm' => 'အချိန်ကန့်သတ် အထူးလျှော့ဈေး'],
'promo_flash_sub' => ['en' => 'Up to 40% Off', 'mm' => '၄၀% အထိလျှော့ချထားသည်'],
'shop_flash_deals' => ['en' => 'Shop Flash Deals →', 'mm' => 'အချိန်ကန့်သတ် အထူးလျှော့ဈေးများ ကြည့်ရန် →'],
'promo_new_collection' => ['en' => 'New Collection', 'mm' => 'ကုန်ပစ္စည်းအသစ်များ'],
'promo_gaming_title' => ['en' => 'Gaming', 'mm' => 'ဂိမ်း'],
'promo_gaming_sub' => ['en' => 'Setup Bundles', 'mm' => 'ဆက်တွဲအစုံများ'],
'explore_now' => ['en' => 'Explore Now →', 'mm' => 'ယခုကြည့်ရှုရန် →'],
'trusted_brands' => ['en' => 'Trusted Brands We Carry', 'mm' => 'ယုံကြည်စိတ်ချရသော ကုန်အမှတ်တံဆိပ်များ'],
'feature_delivery_title' => ['en' => 'Express Delivery', 'mm' => 'အမြန်ပို့ဆောင်ရေး'],
'feature_delivery_desc' => [
    'en' => 'Same-day dispatch on orders before 2PM. Free shipping on orders over €50.',
    'mm' => 'နေ့လည် ၂ နာရီမတိုင်မီ မှာယူပါက ထိုနေ့တွင်ပင် ပို့ဆောင်ပါသည်။ €၅၀ အထက် အော်ဒါများအတွက် ပို့ဆောင်ခ အခမဲ့။',
],
'feature_payments_title' => ['en' => 'Secure Payments', 'mm' => 'လုံခြုံသော ငွေပေးချေမှု'],
'feature_payments_desc' => [
    'en' => '256-bit SSL encryption. Pay with card, PayPal, Apple Pay, or crypto.',
    'mm' => '256-bit SSL လုံခြုံရေးစနစ်။ ကတ်၊ PayPal၊ Apple Pay သို့မဟုတ် ခရစ်တိုဖြင့် ငွေပေးချေနိုင်သည်။',
],
'feature_returns_title' => ['en' => 'Easy Returns', 'mm' => 'လွယ်ကူသော ပြန်အမ်းမှု'],
'feature_returns_desc' => [
    'en' => '30-day hassle-free returns on all products. No questions asked.',
    'mm' => 'ကုန်ပစ္စည်းအားလုံးအတွက် ၃၀ ရက်အတွင်း အခက်အခဲမရှိ ပြန်အမ်းနိုင်ပါသည်။',
],
'feature_warranty_title' => ['en' => '2-Year Warranty', 'mm' => '၂ နှစ် အာမခံ'],
'feature_warranty_desc' => [
    'en' => 'All products covered under our extended warranty program.',
    'mm' => 'ကုန်ပစ္စည်းအားလုံးကို ကျွန်ုပ်တို့၏ တိုးချဲ့အာမခံအစီအစဉ်ဖြင့် ကာကွယ်ပေးထားပါသည်။',
],
'newsletter_title' => ['en' => 'Stay Ahead of the Curve', 'mm' => 'အမြဲအရှေ့ဆုံးမှ သိရှိနေပါ'],
'newsletter_sub' => [
    'en' => 'Get early access to new products, exclusive deals, and tech news directly to your inbox. No spam, ever.',
    'mm' => 'ကုန်ပစ္စည်းအသစ်များ၊ အထူးလျှော့စျေးများနှင့် နည်းပညာသတင်းများကို သင့်အီးမေးလ်သို့ တိုက်ရိုက်ရယူပါ။',
],
'subscribe' => ['en' => 'Subscribe', 'mm' => 'စာရင်းသွင်းရန်'],
'email_placeholder' => ['en' => 'your@email.com', 'mm' => 'your@email.com'],
'subscribed_toast' => ['en' => 'Subscribed! Check your inbox.', 'mm' => 'စာရင်းသွင်းပြီးပါပြီ! သင့်အီးမေးလ်ကို စစ်ဆေးပါ။'],
'item_added_cart' => ['en' => 'Item added to cart', 'mm' => 'ပစ္စည်းကို ဈေး၀ယ်ခြင်းထဲသို့ ထည့်ပြီးပါပြီ'],


/* ══════════════════════════════════════════
   SHOP / PRODUCTS PAGE + SIDEBAR
══════════════════════════════════════════ */
'all_products' => ['en' => 'All Products', 'mm' => 'ကုန်ပစ္စည်းအားလုံး'],
'search_results_for' => ['en' => 'Search', 'mm' => 'ရှာဖွေမှု'],
'product_count' => ['en' => 'product', 'mm' => 'ခု'],
'products_count_plural' => ['en' => 'products', 'mm' => 'ခု'],
'search' => ['en' => 'Search', 'mm' => 'ရှာဖွေရန်'],
'sort' => ['en' => 'Sort', 'mm' => 'စီစဉ်ရန်'],
'sort_newest' => ['en' => 'Newest First', 'mm' => 'အသစ်ဆုံးအရင်'],
'sort_bestseller' => ['en' => 'Best Sellers', 'mm' => 'အရောင်းရဆုံး'],
'sort_price_asc' => ['en' => 'Price: Low → High', 'mm' => 'ဈေးနှုန်း: နည်း → များ'],
'sort_price_desc' => ['en' => 'Price: High → Low', 'mm' => 'ဈေးနှုန်း: များ → နည်း'],
'sort_name_asc' => ['en' => 'Name A – Z', 'mm' => 'အမည် A – Z'],
'showing_x_of_y' => ['en' => 'Showing {x} of {y} products', 'mm' => 'ကုန်ပစ္စည်း {y} ခုအနက် {x} ခု ပြသနေသည်'],
'clear_all' => ['en' => 'Clear all', 'mm' => 'အားလုံးရှင်းရန်'],
'no_products_found' => ['en' => 'No products found', 'mm' => 'ကုန်ပစ္စည်း မတွေ့ပါ'],
'try_adjust_filters' => ['en' => 'Try adjusting your filters or search term', 'mm' => 'သင်၏ စစ်ထုတ်မှု သို့မဟုတ် ရှာဖွေချက်ကို ပြန်ညှိကြည့်ပါ'],
'clear_filters' => ['en' => 'Clear Filters', 'mm' => 'စစ်ထုတ်မှုများ ရှင်းရန်'],
'in_stock_label' => ['en' => 'in stock', 'mm' => 'လက်ကျန်ရှိသည်'],
'only_x_left' => ['en' => 'Only {x} left', 'mm' => '{x} ခုသာ ကျန်တော့သည်'],
'out_of_stock' => ['en' => 'Out of Stock', 'mm' => 'ပစ္စည်းကုန်နေသည်'],

/* Sidebar filters */
'filters' => ['en' => 'Filters', 'mm' => 'စစ်ထုတ်မှုများ'],
'reset' => ['en' => 'Reset', 'mm' => 'ပြန်လည်သတ်မှတ်ရန်'],
'category' => ['en' => 'Category', 'mm' => 'အမျိုးအစား'],
'all_categories' => ['en' => 'All Categories', 'mm' => 'အမျိုးအစားအားလုံး'],
'brand' => ['en' => 'Brand', 'mm' => 'ကုန်အမှတ်တံဆိပ်'],
'no_brands' => ['en' => 'No brands available.', 'mm' => 'ကုန်အမှတ်တံဆိပ် မရှိသေးပါ။'],
'price_range' => ['en' => 'Price Range', 'mm' => 'ဈေးနှုန်းအပိုင်းအခြား'],
'apply_price_filter' => ['en' => 'Apply Price Filter', 'mm' => 'ဈေးနှုန်းစစ်ထုတ်မှု သုံးရန်'],
'in_stock_only' => ['en' => 'In Stock Only', 'mm' => 'လက်ကျန်ရှိသည်များသာ'],
'hide_sold_out' => ['en' => 'Hide sold-out items', 'mm' => 'ပစ္စည်းကုန်သွားသည်များ ဖျောက်ရန်'],
'pg_prev' => ['en' => '← Prev', 'mm' => '← နောက်သို့'],
'pg_next' => ['en' => 'Next →', 'mm' => 'ရှေ့သို့ →'],


/* ══════════════════════════════════════════
   CART PAGE
══════════════════════════════════════════ */
'your_cart' => ['en' => 'Your Cart', 'mm' => 'သင့်ဈေး၀ယ်ခြင်း'],
'item_singular' => ['en' => 'item', 'mm' => 'ခု'],
'item_plural' => ['en' => 'items', 'mm' => 'ခု'],
'cart_empty_title' => ['en' => 'Your cart is empty', 'mm' => 'သင့်ဈေး၀ယ်ခြင်း ဗလာဖြစ်နေသည်'],
'cart_empty_sub' => ["en" => "Looks like you haven't added anything yet.", 'mm' => 'သင် မည်သည့်ပစ္စည်းမျှ မထည့်ရသေးပါ။'],
'start_shopping' => ['en' => 'Start Shopping', 'mm' => 'စျေးဝယ်ရန်စတင်ပါ'],
'th_product' => ['en' => 'Product', 'mm' => 'ကုန်ပစ္စည်း'],
'th_qty' => ['en' => 'Qty', 'mm' => 'အရေအတွက်'],
'th_price' => ['en' => 'Price', 'mm' => 'ဈေးနှုန်း'],
'each_suffix' => ['en' => 'each', 'mm' => 'တစ်ခုလျှင်'],
'only_x_left_bang' => ['en' => 'Only {x} left!', 'mm' => '{x} ခုသာ ကျန်တော့သည်!'],
'continue_shopping' => ['en' => '← Continue Shopping', 'mm' => '← ဆက်လက်ဝယ်ယူရန်'],
'clear_cart' => ['en' => 'Clear Cart', 'mm' => 'ဈေး၀ယ်ခြင်းရှင်းရန်'],
'confirm_clear_cart' => ['en' => 'Clear your entire cart?', 'mm' => 'သင့်ဈေး၀ယ်ခြင်းတစ်ခုလုံးကို ရှင်းလိုပါသလား?'],
'order_summary' => ['en' => 'Order Summary', 'mm' => 'အော်ဒါအကျဉ်းချုပ်'],
'subtotal' => ['en' => 'Subtotal', 'mm' => 'ခွဲစုစုပေါင်း'],
'shipping' => ['en' => 'Shipping', 'mm' => 'ပို့ဆောင်ခ'],
'estimate' => ['en' => '(estimate)', 'mm' => '(ခန့်မှန်း)'],
'free' => ['en' => 'Free', 'mm' => 'အခမဲ့'],
'add_x_more_free_shipping' => ['en' => 'Add €{x} more for free shipping', 'mm' => 'ပို့ဆောင်ခ အခမဲ့ရရန် နောက်ထပ် €{x} ထပ်ဝယ်ပါ'],
'coupon_placeholder' => ['en' => 'Coupon code', 'mm' => 'ကူပွန်ကုဒ်'],
'apply' => ['en' => 'Apply', 'mm' => 'သုံးရန်'],
'total' => ['en' => 'Total', 'mm' => 'စုစုပေါင်း'],
'proceed_to_checkout' => ['en' => 'Proceed to Checkout', 'mm' => 'ငွေရှင်းရန်ဆက်သွားပါ'],
'sign_in_to_checkout' => ['en' => 'Sign In to Checkout', 'mm' => 'ငွေရှင်းရန် ဝင်ရောက်ပါ'],
'cart_saved_notice' => ['en' => 'Your cart will be saved when you sign in.', 'mm' => 'သင်ဝင်ရောက်သောအခါ သင့်ဈေး၀ယ်ခြင်းကို သိမ်းဆည်းပေးပါမည်။'],
'trust_secure_checkout' => ['en' => '🔒 Secure checkout', 'mm' => '🔒 လုံခြုံသောငွေရှင်းမှု'],
'trust_fast_delivery' => ['en' => '🚚 Fast delivery', 'mm' => '🚚 လျင်မြန်သောပို့ဆောင်မှု'],
'trust_easy_returns' => ['en' => '↩️ Easy returns', 'mm' => '↩️ လွယ်ကူသောပြန်အမ်းမှု'],
'please_enter_coupon' => ['en' => 'Please enter a coupon code.', 'mm' => 'ကူပွန်ကုဒ် ထည့်သွင်းပါ။'],
'could_not_apply_coupon' => ['en' => 'Could not apply coupon.', 'mm' => 'ကူပွန်ကို အသုံးမပြုနိုင်ပါ။'],


/* ══════════════════════════════════════════
   CHECKOUT PAGE
══════════════════════════════════════════ */
'checkout' => ['en' => 'Checkout', 'mm' => 'ငွေရှင်းရန်'],
'step_fulfilment' => ['en' => 'Fulfilment Method', 'mm' => 'ပစ္စည်းလက်ခံနည်းလမ်း'],
'home_delivery' => ['en' => 'Home Delivery', 'mm' => 'အိမ်အထိပို့ဆောင်ခြင်း'],
'home_delivery_sub' => ['en' => 'Delivered to your address', 'mm' => 'သင့်လိပ်စာသို့ ပို့ဆောင်ပါမည်'],
'store_pickup' => ['en' => 'Store Pickup', 'mm' => 'ဆိုင်တွင်လာယူခြင်း'],
'store_pickup_sub_ok' => ['en' => 'Free — collect from a nearby store', 'mm' => 'အခမဲ့ — အနီးဆုံးဆိုင်တွင် လာယူပါ'],
'store_pickup_sub_none' => ['en' => 'No pickup stores available', 'mm' => 'လာယူနိုင်သည့်ဆိုင် မရှိသေးပါ'],
'step_delivery_address' => ['en' => 'Delivery Address', 'mm' => 'ပို့ဆောင်ရန်လိပ်စာ'],
'step_pickup_location' => ['en' => 'Pickup Location', 'mm' => 'လာယူရန်နေရာ'],
'use_new_address' => ['en' => '+ Use a new address', 'mm' => '+ လိပ်စာအသစ်သုံးရန်'],
'full_name' => ['en' => 'Full Name', 'mm' => 'အမည်အပြည့်အစုံ'],
'phone' => ['en' => 'Phone', 'mm' => 'ဖုန်းနံပါတ်'],
'address' => ['en' => 'Address', 'mm' => 'လိပ်စာ'],
'street_and_number' => ['en' => 'Street and number', 'mm' => 'လမ်းနှင့်အိမ်နံပါတ်'],
'city' => ['en' => 'City', 'mm' => 'မြို့'],
'select_city' => ['en' => 'Select city', 'mm' => 'မြို့ရွေးပါ'],
'save_address_future' => ['en' => 'Save this address for future orders', 'mm' => 'နောင်အော်ဒါများအတွက် ဤလိပ်စာကို သိမ်းရန်'],
'choose_a_store' => ['en' => 'Choose a store', 'mm' => 'ဆိုင်တစ်ခုရွေးပါ'],
'select_a_store' => ['en' => 'Select a store…', 'mm' => 'ဆိုင်တစ်ခု ရွေးချယ်ပါ…'],
'pickup_ready_hint' => [
    'en' => "You'll receive a pickup-ready notification once your order is prepared.",
    'mm' => 'သင့်အော်ဒါ အဆင်သင့်ဖြစ်သောအခါ အကြောင်းကြားချက် ရရှိပါမည်။',
],
'step_payment' => ['en' => 'Payment Method', 'mm' => 'ငွေပေးချေမှုနည်းလမ်း'],
'pay_kpay_sub' => ['en' => 'Pay with KBZ Pay', 'mm' => 'KBZ Pay ဖြင့်ပေးချေရန်'],
'pay_paypal_sub' => ['en' => 'Pay with your PayPal account', 'mm' => 'သင့် PayPal အကောင့်ဖြင့်ပေးချေရန်'],
'pay_bank_sub' => ['en' => 'SEPA / Wire transfer', 'mm' => 'ဘဏ်လွှဲငွေ'],
'kpay_note' => ['en' => 'Enter your registered KPay phone number to complete payment.', 'mm' => 'ငွေပေးချေမှုပြီးမြောက်ရန် သင့် KPay ဖုန်းနံပါတ်ကို ထည့်ပါ။'],
'kpay_phone_placeholder' => ['en' => '09 xxxx xxxx', 'mm' => '၀၉ xxxx xxxx'],
'step_order_notes' => ['en' => 'Order Notes', 'mm' => 'အော်ဒါမှတ်ချက်'],
'optional' => ['en' => '(optional)', 'mm' => '(မဖြစ်မနေမလိုအပ်)'],
'order_notes_placeholder' => ['en' => 'Special instructions, delivery notes…', 'mm' => 'အထူးညွှန်ကြားချက်များ၊ ပို့ဆောင်မှုမှတ်ချက်များ…'],
'place_order' => ['en' => 'Place Order', 'mm' => 'အော်ဒါတင်ရန်'],
'ssl_secured' => ['en' => '🔒 SSL Secured', 'mm' => '🔒 SSL လုံခြုံမှု'],
'returns_30day' => ['en' => '↩️ 30-day returns', 'mm' => '↩️ ၃၀ ရက်ပြန်အမ်းမှု'],
'back_to_cart' => ['en' => '← Back to Cart', 'mm' => '← ဈေး၀ယ်ခြင်းသို့ပြန်သွားရန်'],
'please_select_pickup' => ['en' => 'Please select a valid pickup location.', 'mm' => 'မှန်ကန်သော လာယူရန်နေရာကို ရွေးချယ်ပါ။'],
'invalid_payment_method' => ['en' => 'Invalid payment method.', 'mm' => 'ငွေပေးချေမှုနည်းလမ်း မှားယွင်းနေသည်။'],
'fill_required_address' => ['en' => 'Please fill in all required address fields.', 'mm' => 'လိုအပ်သောလိပ်စာအချက်အလက်များကို ဖြည့်စွက်ပါ။'],
'checkout_generic_error' => ['en' => 'Something went wrong placing your order. Please try again.', 'mm' => 'အော်ဒါတင်ရာတွင် အမှားအယွင်းရှိခဲ့သည်။ ထပ်စမ်းကြည့်ပါ။'],
'order_placed_title' => ['en' => 'Order Placed!', 'mm' => 'အော်ဒါတင်ပြီးပါပြီ!'],
'order_placed_sub' => ['en' => 'Thank you for your purchase. Your order {id} is being processed', 'mm' => 'ဝယ်ယူပေးသည့်အတွက် ကျေးဇူးတင်ပါသည်။ သင့်အော်ဒါ {id} ကို လုပ်ဆောင်နေပါသည်'],
'order_placed_pickup_suffix' => ['en' => ' and will be ready for pickup soon.', 'mm' => ' ခေတ္တစောင့်ဆိုင်းပါ၊ မကြာမီ လာယူနိုင်ပါမည်။'],
'back_to_home' => ['en' => 'Back to Home', 'mm' => 'ပင်မစာမျက်နှာသို့'],
'track_your_order' => ['en' => 'Track Your Order', 'mm' => 'သင့်အော်ဒါခြေရာခံရန်'],
'order_cancelled_notice' => ['en' => 'This order has been cancelled.', 'mm' => 'ဤအော်ဒါကို ပယ်ဖျက်လိုက်ပါပြီ။'],
'track_processing' => ['en' => 'Processing', 'mm' => 'လုပ်ဆောင်နေဆဲ'],
'track_confirmed' => ['en' => 'Confirmed', 'mm' => 'အတည်ပြုပြီး'],
'track_shipped' => ['en' => 'Shipped', 'mm' => 'ပို့ဆောင်ပြီး'],
'track_delivered' => ['en' => 'Delivered', 'mm' => 'ရောက်ရှိပြီး'],
'items' => ['en' => 'Items', 'mm' => 'ပစ္စည်းများ'],
'store_pickup_tag' => ['en' => '🏬 Store Pickup', 'mm' => '🏬 ဆိုင်တွင်လာယူ'],
'pickup_location' => ['en' => 'Pickup Location', 'mm' => 'လာယူရန်နေရာ'],
'delivery_address' => ['en' => 'Delivery Address', 'mm' => 'ပို့ဆောင်ရန်လိပ်စာ'],
'payment' => ['en' => 'Payment', 'mm' => 'ငွေပေးချေမှု'],


/* ══════════════════════════════════════════
   ORDERS PAGE
══════════════════════════════════════════ */
'my_orders_title' => ['en' => 'My Orders', 'mm' => 'ကျွန်ုပ်၏အော်ဒါများ'],
'x_orders_placed' => ['en' => '{x} order{s} placed', 'mm' => 'အော်ဒါ {x} ခု တင်ထားသည်'],
'continue_shopping_arrow' => ['en' => 'Continue Shopping →', 'mm' => 'ဆက်လက်ဝယ်ယူရန် →'],
'all' => ['en' => 'All', 'mm' => 'အားလုံး'],
'status_processing' => ['en' => 'Processing', 'mm' => 'လုပ်ဆောင်နေဆဲ'],
'status_shipped' => ['en' => 'Shipped', 'mm' => 'ပို့ဆောင်ပြီး'],
'status_delivered' => ['en' => 'Delivered', 'mm' => 'ရောက်ရှိပြီး'],
'status_cancelled' => ['en' => 'Cancelled', 'mm' => 'ပယ်ဖျက်ပြီး'],
'search_order_placeholder' => ['en' => 'Search by order # or product name…', 'mm' => 'အော်ဒါနံပါတ် သို့မဟုတ် ကုန်ပစ္စည်းအမည်ဖြင့် ရှာပါ…'],
'no_orders_found' => ['en' => 'No orders found', 'mm' => 'အော်ဒါ မတွေ့ပါ'],
'try_adjust_search_filter' => ['en' => 'Try adjusting your search or filter.', 'mm' => 'ရှာဖွေမှု သို့မဟုတ် စစ်ထုတ်မှုကို ပြန်ညှိကြည့်ပါ။'],
'no_orders_yet' => ["en" => "You haven't placed any orders yet.", 'mm' => 'သင် အော်ဒါမတင်ရသေးပါ။'],
'clear_filters_link' => ['en' => 'Clear filters', 'mm' => 'စစ်ထုတ်မှုများ ရှင်းရန်'],
'order_hash' => ['en' => 'Order', 'mm' => 'အော်ဒါ'],
'order_items_count' => ['en' => 'Items', 'mm' => 'ပစ္စည်းအရေအတွက်'],
'order_payment' => ['en' => 'Payment', 'mm' => 'ငွေပေးချေမှု'],
'order_total' => ['en' => 'Total', 'mm' => 'စုစုပေါင်း'],
'th_sku' => ['en' => 'SKU', 'mm' => 'SKU'],
'th_unit_price' => ['en' => 'Unit Price', 'mm' => 'တစ်ခုစျေးနှုန်း'],
'view_product' => ['en' => 'View product →', 'mm' => 'ကုန်ပစ္စည်းကြည့်ရန် →'],
'discount' => ['en' => 'Discount', 'mm' => 'လျှော့ဈေး'],
'reorder' => ['en' => '🔄 Reorder', 'mm' => '🔄 ထပ်မံမှာယူရန်'],
'cancel_order' => ['en' => 'Cancel Order', 'mm' => 'အော်ဒါပယ်ဖျက်ရန်'],
'confirm_cancel_order' => ['en' => 'Cancel order #{id}? This cannot be undone.', 'mm' => 'အော်ဒါ #{id} ကို ပယ်ဖျက်မလား? ပြန်ပြင်၍မရပါ။'],
'order_cancelled_toast' => ['en' => 'Order cancelled.', 'mm' => 'အော်ဒါကို ပယ်ဖျက်ပြီးပါပြီ။'],


/* ══════════════════════════════════════════
   WISHLIST PAGE
══════════════════════════════════════════ */
'my_wishlist' => ['en' => 'My Wishlist', 'mm' => 'ကျွန်ုပ်၏ အနှစ်သက်ဆုံးစာရင်း'],
'wishlist_sub' => ["en" => "Products you've saved for later", 'mm' => 'နောင်ဝယ်ရန် သိမ်းထားသောပစ္စည်းများ'],
'add_all_to_cart' => ['en' => 'Add All to Cart', 'mm' => 'အားလုံးကို ဈေး၀ယ်ခြင်းထဲထည့်ရန်'],
'wishlist_empty_title' => ['en' => 'Your wishlist is empty', 'mm' => 'သင့်အနှစ်သက်ဆုံးစာရင်း ဗလာဖြစ်နေသည်'],
'wishlist_empty_sub' => ['en' => 'Save items you love by clicking the heart icon on any product.', 'mm' => 'နှစ်သက်သောပစ္စည်းများကို ကုန်ပစ္စည်းတိုင်းရှိ နှလုံးသားပုံစံကိုနှိပ်ပြီး သိမ်းဆည်းပါ။'],
'browse_products' => ['en' => 'Browse Products', 'mm' => 'ကုန်ပစ္စည်းများကြည့်ရန်'],
'guest_wishlist_notice' => ['en' => "You're browsing as a guest.", 'mm' => 'သင်သည် ဧည့်သည်အနေနှင့် ကြည့်ရှုနေပါသည်။'],
'sign_in_save_wishlist' => ['en' => 'Sign in to save your wishlist permanently.', 'mm' => 'သင့်အနှစ်သက်ဆုံးစာရင်းကို အမြဲထိန်းသိမ်းရန် ဝင်ရောက်ပါ။'],
'in_stock' => ['en' => 'In Stock', 'mm' => 'လက်ကျန်ရှိသည်'],
'in_cart' => ['en' => 'In Cart', 'mm' => 'ဈေး၀ယ်ခြင်းထဲရှိပြီး'],
'clear_wishlist' => ['en' => 'Clear Wishlist', 'mm' => 'စာရင်းရှင်းရန်'],
'confirm_clear_wishlist' => ['en' => 'Remove all items from your wishlist?', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းမှ ပစ္စည်းအားလုံးကို ဖယ်ရှားလိုပါသလား?'],
'removed_from_wishlist' => ['en' => 'Removed from wishlist.', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းမှ ဖယ်ရှားလိုက်ပါပြီ။'],
'saved_to_wishlist' => ['en' => 'Saved to wishlist', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းသို့ သိမ်းလိုက်ပါပြီ'],


/* ══════════════════════════════════════════
   PRODUCT DETAIL PAGE
══════════════════════════════════════════ */
'brand_label' => ['en' => 'Brand', 'mm' => 'ကုန်အမှတ်တံဆိပ်'],
'quantity' => ['en' => 'Quantity', 'mm' => 'အရေအတွက်'],
'max_label' => ['en' => 'Max', 'mm' => 'အများဆုံး'],
'you_might_also_like' => ['en' => 'You Might Also Like', 'mm' => 'သင်ကြိုက်နှစ်သက်နိုင်သည့်ပစ္စည်းများ'],
'view_all_in' => ['en' => 'View all in {cat} →', 'mm' => '{cat} အားလုံးကြည့်ရန် →'],
'about_this_product' => ['en' => 'About this product', 'mm' => 'ဤကုန်ပစ္စည်းအကြောင်း'],
'availability' => ['en' => 'Availability', 'mm' => 'ရရှိနိုင်မှု'],
'sku_label' => ['en' => 'SKU', 'mm' => 'SKU'],
'trust_fast_dispatch' => ['en' => '🚀 Fast Dispatch', 'mm' => '🚀 လျင်မြန်စွာပို့ဆောင်'],
'trust_30_returns' => ['en' => '🔄 30-Day Returns', 'mm' => '🔄 ၃၀ ရက်ပြန်အမ်းမှု'],
'trust_2yr_warranty' => ['en' => '🛡️ 2-Year Warranty', 'mm' => '🛡️ ၂ နှစ်အာမခံ'],
'trust_secure_checkout_2' => ['en' => '🔒 Secure Checkout', 'mm' => '🔒 လုံခြုံသောငွေရှင်း'],
'stock_available' => ['en' => 'In Stock ({x} available)', 'mm' => 'လက်ကျန်ရှိသည် ({x} ခုရရှိနိုင်)'],
'stock_low' => ['en' => 'Only {x} left in stock', 'mm' => '{x} ခုသာ ကျန်တော့သည်'],
'wl_remove_tooltip' => ['en' => 'Remove from wishlist', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းမှ ဖယ်ရှားရန်'],
'wl_add_tooltip' => ['en' => 'Add to wishlist', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းသို့ ထည့်ရန်'],


/* ══════════════════════════════════════════
   DEALS PAGE
══════════════════════════════════════════ */
'limited_time_deals' => ['en' => 'Limited Time Deals', 'mm' => 'အချိန်ကန့်သတ် အထူးလျှော့စျေးများ'],
'save_big_1' => ['en' => 'Save Big on', 'mm' => 'အရည်အသွေးမြင့် နည်းပညာပစ္စည်းများကို'],
'save_big_2' => ['en' => 'Premium Tech', 'mm' => 'အထူးလျှော့ဈေးနဲ့ ဝယ်ယူပါ!'],
'deals_hero_sub' => [
    'en' => "Handpicked discounts on the gear you actually want — refreshed daily. Grab them before they're gone.",
    'mm' => 'သင်အမှန်တကယ်လိုချင်သည့်ပစ္စည်းများအတွက် နေ့စဉ်အသစ်ပြောင်းလဲသော လျှော့စျေးများ — ကုန်မသွားမီ အမြန်ဝယ်ယူပါ။',
],
'flash_sale_ends_in' => ['en' => 'Flash sale ends in', 'mm' => 'အချိန်ကန့်သတ် အထူးလျှော့ဈေး ကုန်ဆုံးရန်'],
'hrs' => ['en' => 'hrs', 'mm' => 'နာရီ'],
'min' => ['en' => 'min', 'mm' => 'မိနစ်'],
'sec' => ['en' => 'sec', 'mm' => 'စက္ကန့်'],
'tab_all_deals' => ['en' => '🔥 All Deals', 'mm' => '🔥 လျှော့စျေးအားလုံး'],
'tab_flash_sale' => ['en' => '⚡ Flash Sale', 'mm' => '⚡ အချိန်ကန့်သတ် အထူးလျှော့ဈေး'],
'tab_clearance' => ['en' => '🏷️ Clearance', 'mm' => '🏷️ လက်ကျန်ပစ္စည်း ရောင်းချမှု'],
'tab_bestsellers' => ['en' => '📦 Best Sellers', 'mm' => '📦 အရောင်းရဆုံး'],
'flash_sale_banner_title' => ['en' => '⚡ Flash Sale — Up to 25% off', 'mm' => '⚡ အချိန်ကန့်သတ် အထူးလျှော့ဈေး — ၂၅% အထိ'],
'flash_sale_banner_sub' => ['en' => 'Today only. These prices disappear at midnight — no extensions, no exceptions.', 'mm' => 'ယနေ့တစ်ရက်သာ။ ဤဈေးနှုန်းများသည် သန်းခေါင်ယံတွင် ကုန်ဆုံးပါမည်။'],
'flash_sale_section_title' => ['en' => 'Flash Sale', 'mm' => 'အချိန်ကန့်သတ် အထူးလျှော့ဈေး'],
'flash_sale_section_sub' => ["en" => "Today's deepest discounts — featured picks at 25% off", 'mm' => 'ယနေ့အတွက် အထူးရွေးချယ်ထားသော ပစ္စည်းများကို ၂၅% လျှော့ဈေးဖြင့် ဝယ်ယူလိုက်ပါ'],
'view_all_featured' => ['en' => 'View all featured', 'mm' => 'အထူးဖော်ပြထားသည်အားလုံးကြည့်ရန်'],
'no_flash_deals' => ['en' => 'No flash deals right now', 'mm' => 'ယခုအချိန်တွင် အချိန်ကန့်သတ် အထူးလျှော့ဈေး မရှိသေးပါ'],
'check_back_soon' => ['en' => 'Check back soon — new deals drop every day.', 'mm' => 'နောက်မှပြန်စစ်ဆေးပါ — နေ့စဉ် လျှော့စျေးအသစ်များ ထွက်ရှိနေပါသည်။'],
'clearance_title' => ['en' => 'Clearance', 'mm' => 'လက်ကျန်ပစ္စည်း ရောင်းချမှု'],
'clearance_sub' => ['en' => 'Limited stock remaining — 15% off while supplies last', 'mm' => 'လက်ကျန်ပစ္စည်းအနည်းငယ်သာ — ၁၅% လျှော့ချထားသည်'],
'browse_all' => ['en' => 'Browse all', 'mm' => 'အားလုံးကြည့်ရန်'],
'clearance_empty_title' => ['en' => 'Clearance is fully stocked', 'mm' => 'လက်ကျန်ပစ္စည်း ရောင်းချမှုပစ္စည်းများ လက်ကျန်အပြည့်ရှိနေသည်'],
'clearance_empty_sub' => ['en' => 'All items are well stocked right now. Check flash and bundle deals above.', 'mm' => 'ယခုအချိန်တွင် ပစ္စည်းအားလုံး လက်ကျန်အပြည့်ရှိနေသည်။'],
'grab_it' => ['en' => 'Grab It', 'mm' => 'ယခုဝယ်ယူပါ'],
'bestsellers_title' => ['en' => 'Best Sellers', 'mm' => 'အရောင်းရဆုံးများ'],
'bestsellers_sub' => ['en' => 'Customer favourites — 20% off our most-sold products', 'mm' => 'ဖောက်သည်အကြိုက်ဆုံးများ — အရောင်းရဆုံးပစ္စည်းများကို ၂၀% လျှော့ချထားသည်'],
'view_bestsellers' => ['en' => 'View bestsellers', 'mm' => 'အရောင်းရဆုံးများကြည့်ရန်'],
'no_bestsellers' => ['en' => 'No bestsellers yet', 'mm' => 'အရောင်းရဆုံးများ မရှိသေးပါ'],
'no_bestsellers_sub' => ['en' => 'Products with sales data will appear here.', 'mm' => 'အရောင်းအချက်အလက်ရှိသော ပစ္စည်းများ ဤနေရာတွင် ပြသပါမည်။'],
'top_seller' => ['en' => 'Top Seller', 'mm' => 'အရောင်းရဆုံး'],
'deal_alerts_title' => ['en' => '🔔 Get deal alerts first', 'mm' => '🔔 လျှော့စျေးအကြောင်းကြားချက်ကို ပထမဆုံးရယူပါ'],
'deal_alerts_sub' => ['en' => 'Flash deals sell out fast. Subscribe to hear about them before everyone else.', 'mm' => 'အချိန်ကန့်သတ် အထူးလျှော့ဈေးများသည် လျင်မြန်စွာ ကုန်ဆုံးတတ်သည်။ လူတိုင်းမတိုင်မီ ရယူရန် စာရင်းသွင်းပါ။'],
'notify_me' => ['en' => 'Notify Me', 'mm' => 'အကြောင်းကြားပါ'],
'save_x' => ['en' => 'Save €{x}', 'mm' => '€{x} ချွေတာ'],
'deal_alert_subscribed' => ["en" => "You're in! Deal alerts coming your way.", 'mm' => 'စာရင်းသွင်းပြီးပါပြီ! လျှော့စျေးအကြောင်းကြားချက်များ ရရှိပါမည်။'],


/* ══════════════════════════════════════════
   FAQ PAGE
══════════════════════════════════════════ */
'faq_title_1' => ['en' => 'Frequently Asked', 'mm' => 'မကြာခဏမေးလေ့ရှိသော'],
'faq_title_2' => ['en' => 'Questions', 'mm' => 'မေးခွန်းများ'],
'faq_hero_sub' => [
    'en' => "Everything you need to know about ordering, shipping, returns, and more. Can't find an answer? We're here to help.",
    'mm' => 'အော်ဒါတင်ခြင်း၊ ပို့ဆောင်ခြင်း၊ ပြန်အမ်းခြင်းနှင့် အခြားအရာများအကြောင်း သင်သိလိုသမျှ။ အဖြေမတွေ့ပါက ကျွန်ုပ်တို့ကူညီပါမည်။',
],
'jump_to' => ['en' => 'Jump to', 'mm' => 'ခုန်ကျော်ရန်'],
'cat_orders' => ['en' => 'Orders', 'mm' => 'အော်ဒါများ'],
'cat_shipping' => ['en' => 'Shipping', 'mm' => 'ပို့ဆောင်ရေး'],
'cat_returns' => ['en' => 'Returns', 'mm' => 'ပြန်အမ်းမှုများ'],
'cat_payments' => ['en' => 'Payments', 'mm' => 'ငွေပေးချေမှုများ'],
'cat_warranty' => ['en' => 'Warranty', 'mm' => 'အာမခံ'],
'cat_account' => ['en' => 'Account', 'mm' => 'အကောင့်'],
'cat_products' => ['en' => 'Products', 'mm' => 'ကုန်ပစ္စည်းများ'],
'no_results_found' => ['en' => 'No results found', 'mm' => 'ရလဒ်မတွေ့ပါ'],
'no_results_sub' => ['en' => 'Try different keywords or browse the categories on the left.', 'mm' => 'အခြားစကားလုံးများဖြင့် ထပ်စမ်းကြည့်ပါ သို့မဟုတ် ဘယ်ဘက်ရှိ အမျိုးအစားများကို ကြည့်ပါ။'],

'faq_o1_q' => ['en' => 'How do I place an order?', 'mm' => 'အော်ဒါကို မည်သို့တင်ရမည်နည်း?'],
'faq_o1_a' => [
    'en' => "Browse our catalogue, add items to your cart, and proceed to checkout. You'll need an account to complete your purchase — it only takes a minute to sign up. We accept all major payment methods and your order will be confirmed by email within a few minutes.",
    'mm' => 'ကျွန်ုပ်တို့၏ ကုန်ပစ္စည်းများကို ကြည့်ရှုပြီး၊ ဈေး၀ယ်ခြင်းထဲသို့ ထည့်ကာ ငွေရှင်းရန်ဆက်သွားပါ။ ဝယ်ယူမှုပြီးမြောက်ရန် အကောင့်လိုအပ်ပြီး၊ စာရင်းသွင်းရန် တစ်မိနစ်သာကြာပါသည်။ အဓိကငွေပေးချေမှုနည်းလမ်းအားလုံးကို လက်ခံပါသည်။ သင့်အော်ဒါကို မိနစ်အနည်းငယ်အတွင်း အီးမေးလ်ဖြင့် အတည်ပြုပေးပါမည်။',
],
'faq_o2_q' => ['en' => 'Can I modify or cancel my order after placing it?', 'mm' => 'အော်ဒါတင်ပြီးနောက် ပြင်ဆင် သို့မဟုတ် ပယ်ဖျက်နိုင်ပါသလား?'],
'faq_o2_a' => [
    'en' => "You have a 30-minute window to modify or cancel an order after placing it. After that, it enters our fulfilment pipeline and changes are no longer possible. Contact our support team immediately if you need to make a change — we'll do our best to help.",
    'mm' => 'အော်ဒါတင်ပြီးနောက် မိနစ် ၃၀ အတွင်း ပြင်ဆင် သို့မဟုတ် ပယ်ဖျက်နိုင်ပါသည်။ ထို့နောက် ပစ္စည်းလုပ်ဆောင်မှုအဆင့်သို့ ရောက်ရှိသွားပြီဖြစ်၍ ပြောင်းလဲ၍မရတော့ပါ။ ပြောင်းလဲလိုပါက ချက်ချင်း အထောက်အပံ့အဖွဲ့ကို ဆက်သွယ်ပါ။',
],
'faq_o3_q' => ['en' => 'How can I track my order?', 'mm' => 'အော်ဒါကို မည်သို့ခြေရာခံနိုင်မည်နည်း?'],
'faq_o3_a' => [
    'en' => "Once your order ships, you'll receive a tracking link by email. You can also visit Track Order in your account dashboard at any time. Tracking updates every 2–4 hours once the parcel is in transit.",
    'mm' => 'သင့်အော်ဒါ ပို့ဆောင်ပြီးသည်နှင့် ခြေရာခံလင့်ခ်ကို အီးမေးလ်ဖြင့် ရရှိပါမည်။ သင့်အကောင့်ဒက်ရှ်ဘုတ်ရှိ Track Order တွင်လည်း ကြည့်နိုင်ပါသည်။ ပစ္စည်းပို့ဆောင်နေစဉ် ၂-၄ နာရီတိုင်း အချက်အလက်အသစ်ပြသပါသည်။',
],
'faq_o4_q' => ['en' => 'What happens if an item in my order goes out of stock?', 'mm' => 'အော်ဒါထဲရှိပစ္စည်းတစ်ခု ပစ္စည်းကုန်သွားပါက ဘာဖြစ်မည်နည်း?'],
'faq_o4_a' => [
    'en' => "In rare cases, an item can sell out between you adding it to your cart and completing checkout. If this happens, we'll notify you immediately and issue a full refund for that item within 1–3 business days. The rest of your order will ship as normal.",
    'mm' => 'ရှားပါးသောအခြေအနေများတွင် သင်ဈေး၀ယ်ခြင်းထဲထည့်ပြီးမှ ထိုပစ္စည်း ကုန်သွားနိုင်ပါသည်။ ဤသို့ဖြစ်ပါက ချက်ချင်းအကြောင်းကြားပြီး ၁-၃ ရုံးဖွင့်ရက်အတွင်း ထိုပစ္စည်းအတွက် အပြည့်အစုံပြန်အမ်းပေးပါမည်။ ကျန်အော်ဒါများကို ပုံမှန်အတိုင်း ပို့ဆောင်ပေးပါမည်။',
],

'faq_s1_q' => ['en' => 'How long does delivery take?', 'mm' => 'ပို့ဆောင်ရန် ဘယ်လောက်ကြာမည်နည်း?'],
'faq_s1_a' => [
    'en' => "Standard delivery: 3–5 business days. Express delivery: next business day if ordered before 2PM. International orders typically arrive within 7–14 business days depending on the destination country and customs clearance.",
    'mm' => 'ပုံမှန်ပို့ဆောင်မှု: ရုံးဖွင့်ရက် ၃-၅ ရက်။ အမြန်ပို့ဆောင်မှု: နေ့လည် ၂ နာရီမတိုင်မီ မှာယူပါက နောက်ရုံးဖွင့်ရက်တွင် ရောက်ပါမည်။ နိုင်ငံတကာအော်ဒါများသည် ခရီးဆုံးနိုင်ငံနှင့် အကောက်ခွန်ကို လိုက်၍ ရုံးဖွင့်ရက် ၇-၁၄ ရက်အတွင်း ရောက်ရှိပါသည်။',
],
'faq_s2_q' => ['en' => 'Is shipping free?', 'mm' => 'ပို့ဆောင်ခ အခမဲ့ဖြစ်ပါသလား?'],
'faq_s2_a' => [
    'en' => 'Yes — free standard shipping on all orders over €50. Orders below €50 have a flat €4.99 shipping fee. Express delivery is €9.99 regardless of order value.',
    'mm' => 'ဟုတ်ပါသည် — €၅၀ အထက် အော်ဒါအားလုံးအတွက် ပုံမှန်ပို့ဆောင်ခ အခမဲ့ဖြစ်ပါသည်။ €၅၀ အောက်အော်ဒါများအတွက် ပို့ဆောင်ခ €၄.၉၉ ကောက်ခံပါသည်။ အမြန်ပို့ဆောင်မှုအတွက် အော်ဒါတန်ဖိုးမရွေး €၉.၉၉ ဖြစ်ပါသည်။',
],
'faq_s3_q' => ['en' => 'Do you ship internationally?', 'mm' => 'နိုင်ငံတကာသို့ ပို့ဆောင်ပေးပါသလား?'],
'faq_s3_a' => [
    'en' => 'We currently ship to 32 countries across Europe, North America, and Australia. Enter your address at checkout to confirm availability and see shipping costs. Some high-value items may require a signature on delivery.',
    'mm' => 'ကျွန်ုပ်တို့သည် ဥရောပ၊ မြောက်အမေရိကနှင့် သြစတြေးလျရှိ နိုင်ငံ ၃၂ ခုသို့ ပို့ဆောင်ပေးလျက်ရှိပါသည်။ ရရှိနိုင်မှုနှင့် ပို့ဆောင်ခကို အတည်ပြုရန် ငွေရှင်းချိန်တွင် သင့်လိပ်စာထည့်သွင်းပါ။',
],
'faq_s4_q' => ['en' => 'What if my package arrives damaged?', 'mm' => 'ပါဆယ် ပျက်စီးပြီးရောက်လာပါက ဘာလုပ်ရမည်နည်း?'],
'faq_s4_a' => [
    'en' => "Take photos of the damaged packaging and item, then contact us within 48 hours of delivery. We'll arrange a free replacement or full refund — whichever you prefer. Do not dispose of the packaging until we've resolved the issue.",
    'mm' => 'ပျက်စီးနေသော ပက်ကေ့ဂျ်နှင့် ပစ္စည်းကို ဓာတ်ပုံရိုက်ပြီး ပို့ဆောင်ပြီးချိန်မှ ၄၈ နာရီအတွင်း ကျွန်ုပ်တို့ကို ဆက်သွယ်ပါ။ အခမဲ့လဲလှယ်ခြင်း သို့မဟုတ် အပြည့်အစုံပြန်အမ်းခြင်း စီစဉ်ပေးပါမည်။',
],

'faq_r1_q' => ['en' => 'What is your return policy?', 'mm' => 'ပြန်အမ်းမှုမူဝါဒ ဘယ်လိုရှိပါသလဲ?'],
'faq_r1_a' => [
    'en' => 'We offer 30-day hassle-free returns on all products, no questions asked. Items must be in their original packaging and unused condition. Simply initiate a return from your account dashboard or contact support.',
    'mm' => 'ကုန်ပစ္စည်းအားလုံးအတွက် ၃၀ ရက်အတွင်း အကြောင်းမေးခွန်းမရှိဘဲ ပြန်အမ်းနိုင်ပါသည်။ ပစ္စည်းများသည် မူလထုပ်ပိုးမှုအတိုင်းနှင့် အသုံးမပြုရသေးသော အခြေအနေတွင် ရှိရပါမည်။',
],
'faq_r2_q' => ['en' => 'How long do refunds take?', 'mm' => 'ငွေပြန်အမ်းမှု ဘယ်လောက်ကြာမည်နည်း?'],
'faq_r2_a' => [
    'en' => "Once we receive your return (usually 3–5 business days after you ship it), we'll inspect it and process your refund within 1–2 business days. The money will appear in your account within 5–10 business days depending on your bank or payment provider.",
    'mm' => 'သင့်ပြန်ပို့သောပစ္စည်းရရှိပြီး (ပုံမှန် ၃-၅ ရုံးဖွင့်ရက်) စစ်ဆေးပြီးနောက် ၁-၂ ရုံးဖွင့်ရက်အတွင်း ငွေပြန်အမ်းမှုကို လုပ်ဆောင်ပေးပါမည်။ ဘဏ်အလိုက် ၅-၁၀ ရုံးဖွင့်ရက်အတွင်း သင့်အကောင့်သို့ ငွေရောက်ရှိပါမည်။',
],
'faq_r3_q' => ['en' => 'Do I have to pay for return shipping?', 'mm' => 'ပြန်ပို့ရန် ပို့ဆောင်ခ ကျွန်ုပ်ပေးရမည်လား?'],
'faq_r3_a' => [
    'en' => 'Return shipping is free if the item is faulty, damaged, or we sent you the wrong product. For change-of-mind returns, a €4.99 return label fee is deducted from your refund. International return shipping costs vary.',
    'mm' => 'ပစ္စည်းချို့ယွင်းမှု၊ ပျက်စီးမှု သို့မဟုတ် အမှားပို့ခဲ့ပါက ပြန်ပို့ခ အခမဲ့ဖြစ်ပါသည်။ စိတ်ပြောင်းလဲမှုကြောင့်ပြန်ပို့ပါက €၄.၉၉ ပြန်ပို့ခကို ငွေပြန်အမ်းမှုမှ နှုတ်ယူပါမည်။',
],

'faq_p1_q' => ['en' => 'What payment methods do you accept?', 'mm' => 'ဘယ်ငွေပေးချေမှုနည်းလမ်းများ လက်ခံပါသလဲ?'],
'faq_p1_a' => [
    'en' => 'We accept Visa, Mastercard, American Express, PayPal, Apple Pay, Google Pay, and selected cryptocurrencies (Bitcoin, Ethereum). All transactions are secured with 256-bit SSL encryption.',
    'mm' => 'Visa၊ Mastercard၊ American Express၊ PayPal၊ Apple Pay၊ Google Pay နှင့် ရွေးချယ်ထားသော ခရစ်တိုငွေကြေးများ (Bitcoin, Ethereum) ကို လက်ခံပါသည်။ ငွေပေးချေမှုအားလုံးကို 256-bit SSL ဖြင့် လုံခြုံစွာကာကွယ်ထားပါသည်။',
],
'faq_p2_q' => ['en' => 'Is my payment information secure?', 'mm' => 'ကျွန်ုပ်၏ငွေပေးချေမှုအချက်အလက် လုံခြုံပါသလား?'],
'faq_p2_a' => [
    'en' => "Absolutely. We never store your card details on our servers. All payment processing is handled by PCI DSS Level 1 compliant payment providers. Look for the padlock icon in your browser's address bar — your connection is always encrypted.",
    'mm' => 'အလုံးစုံလုံခြုံပါသည်။ သင့်ကတ်အချက်အလက်များကို ကျွန်ုပ်တို့ဆာဗာတွင် မသိမ်းဆည်းပါ။ ငွေပေးချေမှုအားလုံးကို PCI DSS Level 1 စံနှုန်းနှင့်ကိုက်ညီသော ငွေပေးချေမှုပံ့ပိုးသူများက ကိုင်တွယ်ပါသည်။',
],
'faq_p3_q' => ['en' => 'Can I pay in instalments?', 'mm' => 'အရစ်ကျ ပေးချေနိုင်ပါသလား?'],
'faq_p3_a' => [
    'en' => 'Yes — for orders over €200, you can split your payment into 3 or 6 monthly instalments at 0% interest through Klarna. Select "Pay Later" at checkout to see your options.',
    'mm' => 'ဟုတ်ပါသည် — €၂၀၀ အထက်အော်ဒါများအတွက် Klarna မှတစ်ဆင့် အတိုးနှုန်း ၀% ဖြင့် လစဉ် ၃ ကြိမ် သို့မဟုတ် ၆ ကြိမ် ခွဲပေးချေနိုင်ပါသည်။',
],

'faq_w1_q' => ['en' => 'What does the 2-year warranty cover?', 'mm' => '၂ နှစ်အာမခံက ဘာတွေ ပါဝင်ပါသလဲ?'],
'faq_w1_a' => [
    'en' => "Our extended warranty covers manufacturing defects, hardware failures, and electrical faults for 2 years from the date of purchase. It does not cover accidental damage, water damage, or normal wear and tear. This is in addition to the manufacturer's own warranty.",
    'mm' => 'ကျွန်ုပ်တို့၏ တိုးချဲ့အာမခံသည် ဝယ်ယူသည့်ရက်မှ ၂ နှစ်အတွင်း ထုတ်လုပ်မှုချို့ယွင်းချက်၊ ဟာ့ဒ်ဝဲချို့ယွင်းမှုနှင့် လျှပ်စစ်ဆိုင်ရာချို့ယွင်းမှုများကို အကာအကွယ်ပေးပါသည်။ မတော်တဆထိခိုက်မှု၊ ရေထိမှု သို့မဟုတ် ပုံမှန်ဆွေးမြေ့မှုများ မပါဝင်ပါ။',
],
'faq_w2_q' => ['en' => 'How do I make a warranty claim?', 'mm' => 'အာမခံတောင်းခံမှု မည်သို့ပြုလုပ်ရမည်နည်း?'],
'faq_w2_a' => [
    'en' => 'Go to Account → My Orders, find the relevant order, and click "Claim Warranty". Describe the issue and attach photos if possible. Our team will review your claim within 2 business days and arrange a repair, replacement, or refund.',
    'mm' => 'Account → My Orders သို့သွားပြီး သက်ဆိုင်ရာအော်ဒါကိုရှာကာ "Claim Warranty" ကိုနှိပ်ပါ။ ပြဿနာကိုဖော်ပြပြီး ဓာတ်ပုံများပူးတွဲပါ။ ကျွန်ုပ်တို့အဖွဲ့က ၂ ရုံးဖွင့်ရက်အတွင်း စစ်ဆေးပြီး ပြင်ဆင်ခြင်း၊ လဲလှယ်ခြင်း သို့မဟုတ် ငွေပြန်အမ်းခြင်း စီစဉ်ပေးပါမည်။',
],

'faq_a1_q' => ['en' => 'How do I reset my password?', 'mm' => 'စကားဝှက်ကို မည်သို့ပြန်လည်သတ်မှတ်ရမည်နည်း?'],
'faq_a1_a' => [
    'en' => "Click Forgot password? on the sign-in page and enter your email address. You'll receive a reset link within a few minutes — check your spam folder if it doesn't appear. The link expires after 24 hours.",
    'mm' => 'ဝင်ရောက်ရန်စာမျက်နှာရှိ Forgot password? ကိုနှိပ်ပြီး သင့်အီးမေးလ်လိပ်စာထည့်ပါ။ မိနစ်အနည်းငယ်အတွင်း ပြန်လည်သတ်မှတ်ရန်လင့်ခ် ရရှိပါမည်။ ၂၄ နာရီအတွင်း သက်တမ်းကုန်ဆုံးပါမည်။',
],
'faq_a2_q' => ['en' => 'Can I have multiple delivery addresses saved?', 'mm' => 'ပို့ဆောင်ရန်လိပ်စာများစွာ သိမ်းဆည်းနိုင်ပါသလား?'],
'faq_a2_a' => [
    'en' => 'Yes. From your account settings you can save up to 5 delivery addresses and set a default. Switching between them at checkout takes a single click.',
    'mm' => 'ဟုတ်ပါသည်။ သင့်အကောင့်ဆက်တင်များမှ လိပ်စာ ၅ ခုအထိ သိမ်းဆည်းနိုင်ပြီး၊ ပုံမှန်လိပ်စာသတ်မှတ်နိုင်ပါသည်။',
],
'faq_a3_q' => ['en' => 'How do I delete my account?', 'mm' => 'အကောင့်ကို မည်သို့ဖျက်ရမည်နည်း?'],
'faq_a3_a' => [
    'en' => 'Go to Account → Settings → Privacy and select "Delete my account". This is permanent and will remove all your data. Any active orders will still be fulfilled. If you have an ongoing warranty claim, please resolve it before deleting your account.',
    'mm' => 'Account → Settings → Privacy သို့သွားပြီး "Delete my account" ကိုရွေးပါ။ ဤလုပ်ဆောင်ချက်သည် အမြဲတမ်းဖြစ်ပြီး သင့်ဒေတာအားလုံးကို ဖျက်ပစ်ပါမည်။',
],

'faq_pr1_q' => ['en' => 'Are all products genuine and brand new?', 'mm' => 'ကုန်ပစ္စည်းအားလုံး စစ်မှန်ပြီး အသစ်များပါသလား?'],
'faq_pr1_a' => [
    'en' => 'Yes. Every product we sell is 100% genuine, sourced directly from authorised brand distributors. We do not sell grey-market, refurbished, or counterfeit products. All items come in original manufacturer packaging.',
    'mm' => 'ဟုတ်ပါသည်။ ကျွန်ုပ်တို့ရောင်းချသော ကုန်ပစ္စည်းအားလုံးသည် တရားဝင်ဖြန့်ဖြူးသူများထံမှ တိုက်ရိုက်ရယူသော ၁၀၀% စစ်မှန်သောပစ္စည်းများဖြစ်ပါသည်။ အသစ်ပြန်၊ အတု သို့မဟုတ် ဈေးကွက်ပြင်ပပစ္စည်းများ မရောင်းချပါ။',
],
'faq_pr2_q' => ["en" => "Can I request a product that isn't listed?", 'mm' => 'ဖော်ပြမထားသောပစ္စည်း တောင်းဆိုနိုင်ပါသလား?'],
'faq_pr2_a' => [
    'en' => 'Absolutely. Use the Product Request form and tell us what you\'re looking for. Our buying team reviews all requests weekly. If it\'s something we can source, we\'ll add it to the catalogue and notify you by email.',
    'mm' => 'ဟုတ်ပါသည်။ Product Request ဖောင်ကိုသုံးပြီး သင်လိုချင်သည့်ပစ္စည်းကို ပြောပြပါ။ ကျွန်ုပ်တို့ဝယ်ယူရေးအဖွဲ့က အပတ်စဉ် စစ်ဆေးပါသည်။',
],
'faq_pr3_q' => ['en' => 'Do you offer price matching?', 'mm' => 'ဈေးနှုန်းတူညီအောင် ချိန်ညှိပေးပါသလား?'],
'faq_pr3_a' => [
    'en' => 'Yes — if you find the same product at a lower price from an authorised retailer, contact us with a link to the listing. We\'ll match the price or beat it by 5%. Price matching applies to in-stock items only and excludes marketplace sellers.',
    'mm' => 'ဟုတ်ပါသည် — တရားဝင်ရောင်းချသူထံမှ ပိုသက်သာသောဈေးနှုန်းတွေ့ရှိပါက လင့်ခ်နှင့်အတူ ကျွန်ုပ်တို့ကို ဆက်သွယ်ပါ။ ဈေးနှုန်းကိုက်ညီအောင် သို့မဟုတ် ၅% ပိုလျှော့ချပေးပါမည်။',
],

'faq_still_questions' => ['en' => 'Still have questions?', 'mm' => 'မေးခွန်းများ ရှိနေသေးပါသလား?'],
'faq_contact_sub' => [
    'en' => 'Our support team is available Monday–Friday, 9AM–6PM CET and typically responds within a few hours.',
    'mm' => 'ကျွန်ုပ်တို့၏ အထောက်အပံ့အဖွဲ့သည် တနင်္လာ-သောကြာ၊ CET နံနက် ၉ နာရီမှ ညနေ ၆ နာရီအထိ ရရှိနိုင်ပြီး၊ များသောအားဖြင့် နာရီအနည်းငယ်အတွင်း ပြန်လည်ဖြေကြားပါသည်။',
],
'email_support' => ['en' => 'Email Support', 'mm' => 'အီးမေးလ်ဖြင့်ဆက်သွယ်ရန်'],
'live_chat' => ['en' => 'Live Chat', 'mm' => 'တိုက်ရိုက်စကားပြော'],


/* ══════════════════════════════════════════
   JS / TOAST MESSAGES  (used via window.LH_I18N in main.js)
══════════════════════════════════════════ */
'js_added_to_cart' => ['en' => '{name} added to cart!', 'mm' => '{name} ကို ဈေး၀ယ်ခြင်းထဲထည့်ပြီးပါပြီ!'],
'js_could_not_add_cart' => ['en' => 'Could not add to cart.', 'mm' => 'ဈေး၀ယ်ခြင်းထဲထည့်၍မရပါ။'],
'js_something_wrong' => ['en' => 'Something went wrong.', 'mm' => 'တစ်ခုခုမှားယွင်းနေပါသည်။'],
'js_saved_wishlist' => ['en' => 'Saved to wishlist', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းသို့ သိမ်းလိုက်ပါပြီ'],
'js_removed_wishlist' => ['en' => 'Removed from wishlist', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းမှ ဖယ်ရှားလိုက်ပါပြီ'],
'js_could_not_update_wishlist' => ['en' => 'Could not update wishlist.', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းကို မပြောင်းလဲနိုင်ပါ။'],
'js_confirm_clear_cart' => ['en' => 'Clear your entire cart?', 'mm' => 'သင့်ဈေး၀ယ်ခြင်းတစ်ခုလုံးကို ရှင်းလိုပါသလား?'],
'js_please_enter_coupon' => ['en' => 'Please enter a coupon code.', 'mm' => 'ကူပွန်ကုဒ်ထည့်ပါ။'],
'js_invalid_coupon' => ['en' => 'Invalid coupon.', 'mm' => 'ကူပွန်မှားယွင်းနေပါသည်။'],
'js_could_not_apply_coupon' => ['en' => 'Could not apply coupon.', 'mm' => 'ကူပွန်ကို အသုံးမပြုနိုင်ပါ။'],
'js_adding' => ['en' => 'Adding…', 'mm' => 'ထည့်နေသည်…'],
'js_in_cart' => ['en' => 'In Cart', 'mm' => 'ဈေး၀ယ်ခြင်းထဲရှိပြီး'],
'js_add_to_cart' => ['en' => 'Add to Cart', 'mm' => 'ဈေး၀ယ်ခြင်းထဲထည့်ရန်'],
'js_items_added_cart' => ['en' => '{n} item(s) added to cart!', 'mm' => 'ပစ္စည်း {n} ခု ဈေး၀ယ်ခြင်းထဲထည့်ပြီးပါပြီ!'],
'js_all_already_in_cart' => ['en' => 'All items already in cart.', 'mm' => 'ပစ္စည်းအားလုံး ဈေး၀ယ်ခြင်းထဲရှိပြီးသားဖြစ်သည်။'],
'js_confirm_clear_wishlist' => ['en' => 'Remove all items from your wishlist?', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းမှ ပစ္စည်းအားလုံးကို ဖယ်ရှားလိုပါသလား?'],
'js_adding_to_cart_ellipsis' => ['en' => 'Adding items to cart…', 'mm' => 'ပစ္စည်းများကို ဈေး၀ယ်ခြင်းထဲထည့်နေသည်…'],
'js_items_added_check' => ['en' => 'Items added to cart!', 'mm' => 'ပစ္စည်းများကို ဈေး၀ယ်ခြင်းထဲထည့်ပြီးပါပြီ!'],
'js_could_not_reorder' => ['en' => 'Could not reorder.', 'mm' => 'ထပ်မံမှာယူ၍မရပါ။'],
'js_confirm_cancel_order' => ['en' => 'Cancel order #{id}? This cannot be undone.', 'mm' => 'အော်ဒါ #{id} ကို ပယ်ဖျက်မလား? ပြန်ပြင်၍မရပါ။'],
'js_order_cancelled' => ['en' => 'Order cancelled.', 'mm' => 'အော်ဒါကို ပယ်ဖျက်ပြီးပါပြီ။'],
'js_could_not_cancel_order' => ['en' => 'Could not cancel order.', 'mm' => 'အော်ဒါကို ပယ်ဖျက်၍မရပါ။'],
'js_subscribed' => ['en' => 'Subscribed! Check your inbox.', 'mm' => 'စာရင်းသွင်းပြီးပါပြီ! သင့်အီးမေးလ်ကိုစစ်ဆေးပါ။'],
'js_deal_alerts_in' => ["en" => "You're in! Deal alerts coming your way.", 'mm' => 'စာရင်းသွင်းပြီးပါပြီ! လျှော့စျေးအကြောင်းကြားချက်များ ရရှိပါမည်။'],
'js_qty_x_added' => ['en' => '{name} × {qty} added to cart', 'mm' => '{name} × {qty} ဈေး၀ယ်ခြင်းထဲထည့်ပြီးပါပြီ'],
'js_could_not_add' => ['en' => 'Could not add to cart', 'mm' => 'ဈေး၀ယ်ခြင်းထဲထည့်၍မရပါ'],
'js_added_redirecting' => ['en' => 'Added to wishlist! Redirecting…', 'mm' => 'အနှစ်သက်ဆုံးစာရင်းထဲထည့်ပြီးပါပြီ! ညွှန်းပို့နေသည်…'],

/* ══════════════════════════════════════════
   ADDITIONAL KEYS — cart.php / checkout.php /
   deals.php / faq.php (previously untranslated)
══════════════════════════════════════════ */
'decrease_qty' => ['en' => 'Decrease quantity', 'mm' => 'အရေအတွက် လျှော့ရန်'],
'increase_qty' => ['en' => 'Increase quantity', 'mm' => 'အရေအတွက် တိုးရန်'],
'remove_item_title' => ['en' => 'Remove item', 'mm' => 'ပစ္စည်းဖယ်ရှားရန်'],
'remove_item_aria' => ['en' => 'Remove {name}', 'mm' => '{name} ကိုဖယ်ရှားရန်'],

'continue_shopping_plain' => ['en' => 'Continue Shopping', 'mm' => 'ဆက်လက်ဝယ်ယူရန်'],
'order_number_hash' => ['en' => 'Order #{id}', 'mm' => 'အော်ဒါ #{id}'],
'kpay_name' => ['en' => 'KPay', 'mm' => 'KPay'],
'paypal_name' => ['en' => 'PayPal', 'mm' => 'PayPal'],
'bank_transfer_name' => ['en' => 'Bank Transfer', 'mm' => 'ဘဏ်လွှဲငွေ'],
'kpay_info_title' => ['en' => '📱 KBZ Pay', 'mm' => '📱 KBZ Pay'],
'kpay_phone_label' => ['en' => 'KPay Phone Number', 'mm' => 'KPay ဖုန်းနံပါတ်'],
'coupon_applied_prefix' => ['en' => 'Coupon applied', 'mm' => 'ကူပွန်အသုံးပြုပြီး'],
'full_name_label' => ['en' => 'Full Name', 'mm' => 'အမည်အပြည့်အစုံ'],
'address_label' => ['en' => 'Address', 'mm' => 'လိပ်စာ'],
'city_label' => ['en' => 'City', 'mm' => 'မြို့'],

'x_left_label' => ['en' => '{x} left', 'mm' => '{x} ခုကျန်'],
'x_sold_label' => ['en' => '{x} sold', 'mm' => '{x} ခုရောင်းပြီး'],
'badge_flash_label' => ['en' => 'Flash', 'mm' => 'အကန့်သတ် အထူးလျှော့ဈေး'],
'badge_clearance_label' => ['en' => 'Clearance', 'mm' => 'လက်ကျန်ပစ္စည်း ရောင်းချမှု'],

]; // end $GLOBALS['LH_I18N']


/* ══════════════════════════════════════════
   HELPERS
══════════════════════════════════════════ */

/**
 * Translate a key for the current session language, with optional
 * {placeholder} substitution. Falls back to English, then to the
 * raw key, so a missing translation never breaks a page.
 */
function t(string $key, array $vars = []): string
{
    $lang = $_SESSION['lang'] ?? 'en';
    $entry = $GLOBALS['LH_I18N'][$key] ?? null;

    $str = $entry[$lang] ?? $entry['en'] ?? $key;

    foreach ($vars as $k => $v) {
        $str = str_replace('{' . $k . '}', (string)$v, $str);
    }

    return $str;
}

/**
 * Returns the full translation table for the current language as a
 * JSON string, for use in main.js (window.LH_I18N). English is used
 * to fill any keys missing for the active language.
 */
function t_json(): string
{
    $lang = $_SESSION['lang'] ?? 'en';
    $out  = [];
    foreach ($GLOBALS['LH_I18N'] as $key => $entry) {
        $out[$key] = $entry[$lang] ?? $entry['en'] ?? $key;
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Current language code, e.g. 'en' or 'mm' — handy for <html lang="..."> */
function current_lang(): string
{
    return $_SESSION['lang'] ?? 'en';
}
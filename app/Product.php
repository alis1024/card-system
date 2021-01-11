<?php
namespace App; use App\Library\Helper; use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Facades\Log as LogWriter; class Product extends Model { protected $guarded = array(); protected $hidden = array(); const ID_API = -1001; const DELIVERY_AUTO = 0; const DELIVERY_MANUAL = 1; const DELIVERY_API = 2; function getUrlAttribute() { return config('app.url') . '/p/' . Helper::id_encode($this->id, Helper::ID_TYPE_PRODUCT); } function getCountAttribute() { return $this->count_all - $this->count_sold; } function category() { return $this->belongsTo(Category::class); } function cards() { return $this->hasMany(Card::class); } function coupons() { return $this->hasMany(Coupon::class); } function orders() { return $this->hasMany(Order::class); } function user() { return $this->belongsTo(User::class); } public static function refreshCount($sp264a55) { \App\Card::where('user_id', $sp264a55->id)->selectRaw('`product_id`,SUM(`count_sold`) as `count_sold`,SUM(`count_all`) as `count_all`')->groupBy('product_id')->orderByRaw('`product_id`')->chunk(1000, function ($spa9e8db) { foreach ($spa9e8db as $sp097637) { $sp6018c8 = \App\Product::where('id', $sp097637->product_id)->first(); if ($sp6018c8) { if ($sp6018c8->delivery === \App\Product::DELIVERY_MANUAL) { $sp6018c8->update(array('count_sold' => $sp097637->count_sold)); } else { $sp6018c8->update(array('count_sold' => $sp097637->count_sold, 'count_all' => $sp097637->count_all)); } } else { } } }); } function createApiCards($spf6b161) { $sp0fda3e = array(); $sp221059 = array(); $sp5004dd = array(); for ($sp9d4bce = 0; $sp9d4bce < $spf6b161->count; $sp9d4bce++) { $sp0fda3e[] = strtoupper(str_random(16)); $sp96327e = date('Y-m-d H:i:s'); switch ($this->id) { case 6: $sp8436ac = 1; break; case 11: $sp8436ac = 2; break; case 37: $sp8436ac = 3; break; default: die('App.Products fatal error#1'); } $sp5004dd[] = array('user_id' => $this->user_id, 'product_id' => $this->id, 'card' => $sp0fda3e[$sp9d4bce], 'type' => \App\Card::TYPE_ONETIME, 'status' => \App\Card::STATUS_NORMAL, 'count_sold' => 0, 'count_all' => 1); $sp221059[] = "(NULL, '{$sp0fda3e[$sp9d4bce]}', '1', '{$sp8436ac}', NULL, NULL, NULL, NULL, NULL, '0', '{$sp96327e}', '0000-00-00 00:00:00')"; } $sp395cb1 = mysqli_connect('localhost', 'udiddz', 'tRihPm3sh6yKedtX', 'udiddz', '3306'); $spd8a1ef = 'INSERT INTO `udiddz`.`ac_kms` (`id`, `km`, `value`, `task`, `udid`, `diz`, `task_id`, `install_url`, `plist_url`, `jh`, `addtime`, `tjtime`) VALUES ' . join(',', $sp221059); $sp1a1701 = mysqli_query($sp395cb1, $spd8a1ef); if (!$sp1a1701) { LogWriter::error('App.Products, connect udid database failed', array('sql' => $spd8a1ef, 'error' => mysqli_error($sp395cb1))); return array(); } $this->count_all += $spf6b161->count; return $this->cards()->createMany($sp5004dd); } function setForShop($sp264a55 = null) { $sp6018c8 = $this; $spbefb16 = $sp6018c8->count; $spfb0496 = $sp6018c8->inventory; if ($spfb0496 == User::INVENTORY_AUTO) { $spfb0496 = System::_getInt('shop_inventory'); } if ($spfb0496 == User::INVENTORY_RANGE) { if ($spbefb16 <= 0) { $sp3aea6b = '不足'; } elseif ($spbefb16 <= 10) { $sp3aea6b = '少量'; } elseif ($spbefb16 <= 20) { $sp3aea6b = '一般'; } else { $sp3aea6b = '大量'; } $sp6018c8->setAttribute('count2', $sp3aea6b); } else { $sp6018c8->setAttribute('count2', $spbefb16); } $sp6018c8->setAttribute('count', $spbefb16); $sp6018c8->setVisible(array('id', 'name', 'description', 'fields', 'delivery', 'count', 'count2', 'buy_min', 'buy_max', 'support_coupon', 'password_open', 'price', 'price_whole')); } }
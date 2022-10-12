<?php
namespace App; use App\Jobs\OrderSms; use App\Library\LogHelper; use App\Mail\OrderShipped; use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Facades\Mail; use Illuminate\Support\Facades\Log as LogWriter; class Order extends Model { protected $guarded = array(); const STATUS_UNPAY = 0; const STATUS_PAID = 1; const STATUS_SUCCESS = 2; const STATUS_FROZEN = 3; const STATUS_REFUND = 4; const STATUS = array(0 => '未支付', 1 => '未发货', 2 => '已发货', 3 => '已冻结', 4 => '已退款'); const SEND_STATUS_UN = 0; const SEND_STATUS_EMAIL_SUCCESS = 1; const SEND_STATUS_EMAIL_FAILED = 2; const SEND_STATUS_MOBILE_SUCCESS = 3; const SEND_STATUS_MOBILE_FAILED = 4; const SEND_STATUS_CARD_UN = 100; const SEND_STATUS_CARD_PROCESSING = 101; const SEND_STATUS_CARD_SUCCESS = 102; const SEND_STATUS_CARD_FAILED = 103; protected $casts = array('api_info' => 'array'); public static function unique_no() { $sp0983aa = date('YmdHis') . str_random(5); while (\App\Order::where('order_no', $sp0983aa)->exists()) { $sp0983aa = date('YmdHis') . str_random(5); } return $sp0983aa; } function user() { return $this->belongsTo(User::class); } function product() { return $this->belongsTo(Product::class); } function pay() { return $this->belongsTo(Pay::class); } function cards() { $sp65322c = $this->belongsToMany(Card::class); return $sp65322c->withTrashed(); } function card_orders() { return $this->hasMany(CardOrder::class); } function fundRecord() { return $this->hasMany(FundRecord::class); } function getCardsArray() { $spc3aa7c = array(); $this->cards->each(function ($sp2173f5) use(&$spc3aa7c) { $spc3aa7c[] = $sp2173f5->card; }); return $spc3aa7c; } function getSendMessage() { if (count($this->cards)) { if (count($this->cards) == $this->count) { $spf76f31 = '订单#' . $this->order_no . '&nbsp;已支付，您购买的内容如下：'; } else { if ($this->cards[0]->type === \App\Card::TYPE_REPEAT || @$this->product->delivery === \App\Product::DELIVERY_MANUAL) { $spf76f31 = '订单#' . $this->order_no . '&nbsp;已支付，您购买的内容如下：'; } else { $spf76f31 = '订单#' . $this->order_no . '&nbsp;已支付，目前库存不足，您还有' . ($this->count - count($this->cards)) . '件未发货，请联系商家客服发货，'; $spf76f31 .= '商家客服QQ：<a href="http://wpa.qq.com/msgrd?v=3&uin=' . $this->user->qq . '&site=qq&menu=yes" target="_blank">' . $this->user->qq . '</a><br>'; $spf76f31 .= '已发货商品见下方：'; } } } else { $spf76f31 = '订单#' . $this->order_no . '&nbsp;已支付，目前库存不足，您购买的' . ($this->count - count($this->cards)) . '件未发货，请联系商家客服发货<br>'; $spf76f31 .= '商家客服QQ：<a href="http://wpa.qq.com/msgrd?v=3&uin=' . $this->user->qq . '&site=qq&menu=yes" target="_blank">' . $this->user->qq . '</a>'; } return $spf76f31; } function sendEmail($sp6da4b4 = false) { if ($sp6da4b4 === false) { $sp6da4b4 = @json_decode($this->contact_ext)['_mail']; } if (!$sp6da4b4 || !@filter_var($sp6da4b4, FILTER_VALIDATE_EMAIL)) { return; } $spc3aa7c = $this->getCardsArray(); try { Mail::to($sp6da4b4)->Queue(new OrderShipped($this, $this->getSendMessage(), join('<br>', $spc3aa7c))); $this->send_status = \App\Order::SEND_STATUS_EMAIL_SUCCESS; $this->saveOrFail(); } catch (\Throwable $spd118f7) { $this->send_status = \App\Order::SEND_STATUS_EMAIL_FAILED; $this->saveOrFail(); LogHelper::setLogFile('mail'); LogWriter::error('Order.sendEmail error', array('order_no' => $this->order_no, 'email' => $sp6da4b4, 'cards' => $spc3aa7c, 'exception' => $spd118f7->getMessage())); LogHelper::setLogFile('card'); } } function sendSms($spbb3ff5 = false) { if ($spbb3ff5 === false) { $spbb3ff5 = @json_decode($this->contact_ext)['_mobile']; } if (!$spbb3ff5 || strlen($spbb3ff5) !== 11) { return; } OrderSms::dispatch($spbb3ff5, $this); } }
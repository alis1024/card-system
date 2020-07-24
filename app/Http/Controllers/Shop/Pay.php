<?php
namespace App\Http\Controllers\Shop; use App\Card; use App\Category; use App\Library\FundHelper; use App\Library\Helper; use App\Library\LogHelper; use App\Product; use App\Library\Response; use Gateway\Pay\Pay as GatewayPay; use App\Library\Geetest; use App\Mail\ProductCountWarn; use App\System; use Carbon\Carbon; use Illuminate\Http\Request; use App\Http\Controllers\Controller; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Log; use Illuminate\Support\Facades\Mail; class Pay extends Controller { public function __construct() { define('SYS_NAME', config('app.name')); define('SYS_URL', config('app.url')); define('SYS_URL_API', config('app.url_api')); } private $payApi = null; public function goPay($spbaac90, $sp40aadb, $sp1eac55, $spb2861f, $sp822fca) { try { (new GatewayPay())->goPay($spb2861f, $sp40aadb, $sp1eac55, $sp1eac55, $sp822fca); return self::renderResultPage($spbaac90, array('success' => false, 'title' => '请稍后', 'msg' => '支付方式加载中，请稍后')); } catch (\Exception $sp696863) { return self::renderResultPage($spbaac90, array('msg' => $sp696863->getMessage())); } } function buy(Request $spbaac90) { $spf97594 = $spbaac90->input('customer'); if (strlen($spf97594) !== 32) { return self::renderResultPage($spbaac90, array('msg' => '提交超时，请刷新购买页面并重新提交<br><br>
当前网址: ' . $spbaac90->getQueryString() . '
提交内容: ' . var_export($spf97594) . ', 提交长度:' . strlen($spf97594) . '<br>
若您刷新后仍然出现此问题. 请加网站客服反馈')); } if ((int) System::_get('vcode_shop_buy') === 1) { $sp599084 = Geetest\API::verify($spbaac90->input('geetest_challenge'), $spbaac90->input('geetest_validate'), $spbaac90->input('geetest_seccode')); if (!$sp599084) { return self::renderResultPage($spbaac90, array('msg' => '滑动验证超时，请返回页面重试。')); } } $sp35f37e = (int) $spbaac90->input('category_id'); $sp1849cc = (int) $spbaac90->input('product_id'); $spe71595 = (int) $spbaac90->input('count'); $spdfcf19 = $spbaac90->input('coupon'); $spce4f4d = $spbaac90->input('contact'); $sp8ed4c0 = $spbaac90->input('contact_ext') ?? null; $sp57e39e = !empty(@json_decode($sp8ed4c0, true)['_mobile']); $spb5c448 = (int) $spbaac90->input('pay_id'); if (!$sp35f37e || !$sp1849cc) { return self::renderResultPage($spbaac90, array('msg' => '请选择商品')); } if (strlen($spce4f4d) < 1) { return self::renderResultPage($spbaac90, array('msg' => '请输入联系方式')); } $sp7a6bf9 = Category::findOrFail($sp35f37e); $sp73d110 = Product::where('id', $sp1849cc)->where('category_id', $sp35f37e)->where('enabled', 1)->with(array('user'))->first(); if ($sp73d110 == null || $sp73d110->user == null) { return self::renderResultPage($spbaac90, array('msg' => '该商品未找到，请重新选择')); } if ($sp73d110->password_open) { if ($sp73d110->password !== $spbaac90->input('product_password')) { return self::renderResultPage($spbaac90, array('msg' => '商品密码输入错误')); } } else { if ($sp7a6bf9->password_open) { if ($sp7a6bf9->password !== $spbaac90->input('category_password')) { if ($sp7a6bf9->getTmpPassword() !== $spbaac90->input('category_password')) { return self::renderResultPage($spbaac90, array('msg' => '分类密码输入错误')); } } } } if ($spe71595 < $sp73d110->buy_min) { return self::renderResultPage($spbaac90, array('msg' => '该商品最少购买' . $sp73d110->buy_min . '件，请重新选择')); } if ($spe71595 > $sp73d110->buy_max) { return self::renderResultPage($spbaac90, array('msg' => '该商品限购' . $sp73d110->buy_max . '件，请重新选择')); } if ($sp73d110->count < $spe71595) { return self::renderResultPage($spbaac90, array('msg' => '该商品库存不足')); } $sp0aa32b = \App\Pay::find($spb5c448); if ($sp0aa32b == null || !$sp0aa32b->enabled) { return self::renderResultPage($spbaac90, array('msg' => '支付方式未找到，请重新选择')); } $spe1a457 = $sp73d110->price; if ($sp73d110->price_whole) { $spfacff6 = json_decode($sp73d110->price_whole, true); for ($sp626673 = count($spfacff6) - 1; $sp626673 >= 0; $sp626673--) { if ($spe71595 >= (int) $spfacff6[$sp626673][0]) { $spe1a457 = (int) $spfacff6[$sp626673][1]; break; } } } $spe0d52b = $spe71595 * $spe1a457; $sp822fca = $spe0d52b; $sp0cfb25 = 0; $spf125cf = null; if ($sp73d110->support_coupon && strlen($spdfcf19) > 0) { $spb38f3f = \App\Coupon::where('user_id', $sp73d110->user_id)->where('coupon', $spdfcf19)->where('expire_at', '>', Carbon::now())->whereRaw('`count_used`<`count_all`')->get(); foreach ($spb38f3f as $spcfdfcc) { if ($spcfdfcc->category_id === -1 || $spcfdfcc->category_id === $sp35f37e && ($spcfdfcc->product_id === -1 || $spcfdfcc->product_id === $sp1849cc)) { if ($spcfdfcc->discount_type === \App\Coupon::DISCOUNT_TYPE_AMOUNT && $sp822fca >= $spcfdfcc->discount_val) { $spf125cf = $spcfdfcc; $sp0cfb25 = $spcfdfcc->discount_val; break; } if ($spcfdfcc->discount_type === \App\Coupon::DISCOUNT_TYPE_PERCENT) { $spf125cf = $spcfdfcc; $sp0cfb25 = (int) round($sp822fca * $spcfdfcc->discount_val / 100); break; } } } if ($spf125cf === null) { return self::renderResultPage($spbaac90, array('msg' => '优惠券信息错误，请重新输入')); } $sp822fca -= $sp0cfb25; } $spd2c14e = (int) round($sp822fca * $sp0aa32b->fee_system); $spd6b033 = $sp822fca - $spd2c14e; $sp768a33 = $sp57e39e ? System::_getInt('sms_price', 10) : 0; $sp822fca += $sp768a33; $sp313203 = $spe71595 * $sp73d110->cost; $sp40aadb = \App\Order::unique_no(); try { DB::transaction(function () use($sp73d110, $sp40aadb, $spf125cf, $spce4f4d, $sp8ed4c0, $spf97594, $spe71595, $sp313203, $spe0d52b, $sp768a33, $sp0cfb25, $sp822fca, $sp0aa32b, $spd2c14e, $spd6b033) { if ($spf125cf) { $spf125cf->status = \App\Coupon::STATUS_USED; $spf125cf->count_used++; $spf125cf->save(); $sp35802c = '使用优惠券: ' . $spf125cf->coupon; } else { $sp35802c = null; } $spb656d1 = \App\Order::create(array('user_id' => $sp73d110->user_id, 'order_no' => $sp40aadb, 'product_id' => $sp73d110->id, 'product_name' => $sp73d110->name, 'count' => $spe71595, 'ip' => Helper::getIP(), 'customer' => $spf97594, 'contact' => $spce4f4d, 'contact_ext' => $sp8ed4c0, 'cost' => $sp313203, 'price' => $spe0d52b, 'sms_price' => $sp768a33, 'discount' => $sp0cfb25, 'paid' => $sp822fca, 'pay_id' => $sp0aa32b->id, 'fee' => $spd2c14e, 'system_fee' => $spd2c14e, 'income' => $spd6b033, 'status' => \App\Order::STATUS_UNPAY, 'remark' => $sp35802c, 'created_at' => Carbon::now())); assert($spb656d1 !== null); }); } catch (\Throwable $sp696863) { Log::error('Shop.Pay.buy 下单失败', array('Exception' => $sp696863)); return self::renderResultPage($spbaac90, array('msg' => '发生错误，下单失败，请稍后重试')); } if ($sp822fca === 0) { $this->shipOrder($spbaac90, $sp40aadb, $sp822fca, null); return redirect('/pay/result/' . $sp40aadb); } $sp1eac55 = $sp40aadb; return $this->goPay($spbaac90, $sp40aadb, $sp1eac55, $sp0aa32b, $sp822fca); } function pay(Request $spbaac90, $sp40aadb) { $spb656d1 = \App\Order::whereOrderNo($sp40aadb)->first(); if ($spb656d1 == null) { return self::renderResultPage($spbaac90, array('msg' => '订单未找到，请重试')); } if ($spb656d1->status !== \App\Order::STATUS_UNPAY) { return redirect('/pay/result/' . $sp40aadb); } $sp3a44ce = 'pay: ' . $spb656d1->pay_id; $spb2861f = $spb656d1->pay; if (!$spb2861f) { \Log::error($sp3a44ce . ' cannot find Pay'); return $this->renderResultPage($spbaac90, array('msg' => '支付方式未找到')); } $sp3a44ce .= ',' . $spb2861f->driver; $sp4259fe = json_decode($spb2861f->config, true); $sp4259fe['payway'] = $spb2861f->way; $sp4259fe['out_trade_no'] = $sp40aadb; try { $this->payApi = GatewayPay::getDriver($spb2861f->id, $spb2861f->driver); } catch (\Exception $sp696863) { \Log::error($sp3a44ce . ' cannot find Driver: ' . $sp696863->getMessage()); return $this->renderResultPage($spbaac90, array('msg' => '支付驱动未找到')); } if ($this->payApi->verify($sp4259fe, function ($sp40aadb, $sp772a1f, $sp318440) use($spbaac90) { try { $this->shipOrder($spbaac90, $sp40aadb, $sp772a1f, $sp318440); } catch (\Exception $sp696863) { $this->renderResultPage($spbaac90, array('success' => false, 'msg' => $sp696863->getMessage())); } })) { \Log::notice($sp3a44ce . ' already success' . '

'); return redirect('/pay/result/' . $sp40aadb); } if ($spb656d1->created_at < Carbon::now()->addMinutes(-5)) { return $this->renderResultPage($spbaac90, array('msg' => '当前订单长时间未支付已作废, 请重新下单')); } $sp73d110 = Product::where('id', $spb656d1->product_id)->where('enabled', 1)->first(); if ($sp73d110 == null) { return self::renderResultPage($spbaac90, array('msg' => '该商品已下架')); } $sp73d110->setAttribute('count', count($sp73d110->cards) ? $sp73d110->cards[0]->count : 0); if ($sp73d110->count < $spb656d1->count) { return self::renderResultPage($spbaac90, array('msg' => '该商品库存不足')); } $sp1eac55 = $sp40aadb; return $this->goPay($spbaac90, $sp40aadb, $sp1eac55, $spb2861f, $spb656d1->paid); } function qrcode(Request $spbaac90, $sp40aadb, $sp5929f9) { $spb656d1 = \App\Order::whereOrderNo($sp40aadb)->with('product')->first(); if ($spb656d1 == null) { return self::renderResultPage($spbaac90, array('msg' => '订单未找到，请重试')); } if ($spb656d1->product_id !== \App\Product::ID_API && $spb656d1->product == null) { return self::renderResultPage($spbaac90, array('msg' => '商品未找到，请重试')); } return view('pay/' . $sp5929f9, array('pay_id' => $spb656d1->pay_id, 'name' => $spb656d1->product->name . ' x ' . $spb656d1->count . '件', 'amount' => $spb656d1->paid, 'qrcode' => $spbaac90->get('url'), 'id' => $sp40aadb)); } function qrQuery(Request $spbaac90, $spb5c448) { $sp9ad4c7 = $spbaac90->input('id', ''); return self::payReturn($spbaac90, $spb5c448, $sp9ad4c7); } function payReturn(Request $spbaac90, $spb5c448, $spe7cd60 = '') { $sp3a44ce = 'payReturn: ' . $spb5c448; \Log::debug($sp3a44ce); $spb2861f = \App\Pay::where('id', $spb5c448)->first(); if (!$spb2861f) { return $this->renderResultPage($spbaac90, array('success' => 0, 'msg' => '支付方式错误')); } $sp3a44ce .= ',' . $spb2861f->driver; if (strlen($spe7cd60) > 0) { $spb656d1 = \App\Order::whereOrderNo($spe7cd60)->first(); if ($spb656d1 && ($spb656d1->status === \App\Order::STATUS_PAID || $spb656d1->status === \App\Order::STATUS_SUCCESS)) { \Log::notice($sp3a44ce . ' already success' . '

'); if ($spbaac90->ajax()) { return self::renderResultPage($spbaac90, array('success' => 1, 'data' => '/pay/result/' . $spe7cd60), array('order' => $spb656d1)); } else { return redirect('/pay/result/' . $spe7cd60); } } } try { $this->payApi = GatewayPay::getDriver($spb2861f->id, $spb2861f->driver); } catch (\Exception $sp696863) { \Log::error($sp3a44ce . ' cannot find Driver: ' . $sp696863->getMessage()); return $this->renderResultPage($spbaac90, array('success' => 0, 'msg' => '支付驱动未找到')); } $sp4259fe = json_decode($spb2861f->config, true); $sp4259fe['out_trade_no'] = $spe7cd60; $sp4259fe['payway'] = $spb2861f->way; \Log::debug($sp3a44ce . ' will verify'); if ($this->payApi->verify($sp4259fe, function ($sp40aadb, $sp772a1f, $sp318440) use($spbaac90, $sp3a44ce, &$spe7cd60) { $spe7cd60 = $sp40aadb; try { \Log::debug($sp3a44ce . " shipOrder start, order_no: {$sp40aadb}, amount: {$sp772a1f}, trade_no: {$sp318440}"); $this->shipOrder($spbaac90, $sp40aadb, $sp772a1f, $sp318440); \Log::debug($sp3a44ce . ' shipOrder end, order_no: ' . $sp40aadb); } catch (\Exception $sp696863) { \Log::error($sp3a44ce . ' shipOrder Exception: ' . $sp696863->getMessage()); } })) { \Log::debug($sp3a44ce . ' verify finished: 1' . '

'); if ($spbaac90->ajax()) { return self::renderResultPage($spbaac90, array('success' => 1, 'data' => '/pay/result/' . $spe7cd60)); } else { return redirect('/pay/result/' . $spe7cd60); } } else { \Log::debug($sp3a44ce . ' verify finished: 0' . '

'); return $this->renderResultPage($spbaac90, array('success' => 0, 'msg' => '支付验证失败，您可以稍后查看支付状态。')); } } function payNotify(Request $spbaac90, $spb5c448) { $sp3a44ce = 'payNotify pay_id: ' . $spb5c448; \Log::debug($sp3a44ce); $spb2861f = \App\Pay::where('id', $spb5c448)->first(); if (!$spb2861f) { \Log::error($sp3a44ce . ' cannot find PayModel'); echo 'fail'; die; } $sp3a44ce .= ',' . $spb2861f->driver; try { $this->payApi = GatewayPay::getDriver($spb2861f->id, $spb2861f->driver); } catch (\Exception $sp696863) { \Log::error($sp3a44ce . ' cannot find Driver: ' . $sp696863->getMessage()); echo 'fail'; die; } $sp4259fe = json_decode($spb2861f->config, true); $sp4259fe['payway'] = $spb2861f->way; $sp4259fe['isNotify'] = true; \Log::debug($sp3a44ce . ' will verify'); $sp599084 = $this->payApi->verify($sp4259fe, function ($sp40aadb, $sp772a1f, $sp318440) use($spbaac90, $sp3a44ce) { try { \Log::debug($sp3a44ce . " shipOrder start, order_no: {$sp40aadb}, amount: {$sp772a1f}, trade_no: {$sp318440}"); $this->shipOrder($spbaac90, $sp40aadb, $sp772a1f, $sp318440); \Log::debug($sp3a44ce . ' shipOrder end, order_no: ' . $sp40aadb); } catch (\Exception $sp696863) { \Log::error($sp3a44ce . ' shipOrder Exception: ' . $sp696863->getMessage()); } }); \Log::debug($sp3a44ce . ' notify finished: ' . (int) $sp599084 . '

'); die; } function result(Request $spbaac90, $sp40aadb) { $spb656d1 = \App\Order::where('order_no', $sp40aadb)->first(); if ($spb656d1 == null) { return self::renderResultPage($spbaac90, array('msg' => '订单未找到，请重试')); } if ($spb656d1->status === \App\Order::STATUS_PAID) { $sp7880b1 = $spb656d1->user->qq; if ($spb656d1->product->delivery === \App\Product::DELIVERY_MANUAL) { $spe765e4 = '您购买的为手动充值商品，请耐心等待处理'; } else { $spe765e4 = '商家库存不足，因此没有自动发货，请联系商家客服发货'; } if ($sp7880b1) { $spe765e4 .= '<br><a href="http://wpa.qq.com/msgrd?v=3&uin=' . $sp7880b1 . '&site=qq&menu=yes" target="_blank">客服QQ:' . $sp7880b1 . '</a>'; } return self::renderResultPage($spbaac90, array('success' => false, 'title' => '订单已支付', 'msg' => $spe765e4), array('order' => $spb656d1)); } elseif ($spb656d1->status === \App\Order::STATUS_SUCCESS) { return self::showOrderResult($spbaac90, $spb656d1); } return self::renderResultPage($spbaac90, array('success' => false, 'msg' => $spb656d1->remark ? '失败原因:<br>' . $spb656d1->remark : '订单支付失败，请重试'), array('order' => $spb656d1)); } function renderResultPage(Request $spbaac90, $spa99c85, $sp165a1a = array()) { if ($spbaac90->ajax()) { if (@$spa99c85['success']) { return Response::success($spa99c85['data']); } else { return Response::fail('error', $spa99c85['msg']); } } else { return view('pay.result', array_merge(array('result' => $spa99c85, 'data' => $sp165a1a), $sp165a1a)); } } function shipOrder($spbaac90, $sp40aadb, $sp772a1f, $sp318440) { $spb656d1 = \App\Order::whereOrderNo($sp40aadb)->first(); if ($spb656d1 === null) { \Log::error('shipOrder: No query results for model [App\\Order:' . $sp40aadb . ',trade_no:' . $sp318440 . ',amount:' . $sp772a1f . ']. die(\'success\');'); die('success'); } if ($spb656d1->paid > $sp772a1f) { \Log::alert('shipOrder, price may error, order_no:' . $sp40aadb . ', paid:' . $spb656d1->paid . ', $amount get:' . $sp772a1f); $spb656d1->remark = '支付金额(' . sprintf('%0.2f', $sp772a1f / 100) . ') 小于 订单金额(' . sprintf('%0.2f', $spb656d1->paid / 100) . ')'; $spb656d1->save(); throw new \Exception($spb656d1->remark); } $sp73d110 = null; if ($spb656d1->status === \App\Order::STATUS_UNPAY) { \Log::debug('shipOrder.first_process:' . $sp40aadb); $sp1d65ca = $spb656d1->id; if (FundHelper::orderSuccess($spb656d1->id, function ($sp6c2a78) use($sp1d65ca, $sp318440, &$spb656d1, &$sp73d110) { $spb656d1 = $sp6c2a78; if ($spb656d1->status !== \App\Order::STATUS_UNPAY) { \Log::debug('Shop.Pay.shipOrder: .first_process:' . $spb656d1->order_no . ' already processed! #2'); return false; } $sp73d110 = $spb656d1->product()->lockForUpdate()->firstOrFail(); $spb656d1->pay_trade_no = $sp318440; $spb656d1->paid_at = Carbon::now(); if ($sp73d110->delivery === \App\Product::DELIVERY_MANUAL) { $spb656d1->status = \App\Order::STATUS_PAID; $spb656d1->send_status = \App\Order::SEND_STATUS_CARD_UN; $spb656d1->saveOrFail(); return true; } $sp93fc77 = Card::where('product_id', $spb656d1->product_id)->whereRaw('`count_sold`<`count_all`')->take($spb656d1->count)->lockForUpdate()->get(); if (count($sp93fc77) !== $spb656d1->count) { Log::alert('Shop.Pay.shipOrder: 订单:' . $spb656d1->order_no . ', 购买数量:' . $spb656d1->count . ', 卡数量:' . count($sp93fc77) . ' 卡密不足(已支付 未发货)'); $spb656d1->status = \App\Order::STATUS_PAID; $spb656d1->saveOrFail(); return true; } else { $spb1562b = array(); foreach ($sp93fc77 as $sp836f4b) { $spb1562b[] = $sp836f4b->id; } $spb656d1->cards()->attach($spb1562b); Card::whereIn('id', $spb1562b)->update(array('status' => Card::STATUS_SOLD, 'count_sold' => DB::raw('`count_sold`+1'))); $spb656d1->status = \App\Order::STATUS_SUCCESS; $spb656d1->saveOrFail(); $sp73d110->count_sold += $spb656d1->count; $sp73d110->saveOrFail(); return FundHelper::ACTION_CONTINUE; } })) { if ($sp73d110->count_warn > 0 && $sp73d110->count < $sp73d110->count_warn) { try { Mail::to($spb656d1->user->email)->Queue(new ProductCountWarn($sp73d110, $sp73d110->count)); } catch (\Throwable $sp696863) { LogHelper::setLogFile('mail'); Log::error('shipOrder.count_warn error', array('product_id' => $spb656d1->product_id, 'email' => $spb656d1->user->email, 'exception' => $sp696863->getMessage())); LogHelper::setLogFile('card'); } } if (System::_getInt('mail_send_order')) { $sp7d1f72 = @json_decode($spb656d1->contact_ext, true)['_mail']; if ($sp7d1f72) { $spb656d1->sendEmail($sp7d1f72); } } if ($spb656d1->status === \App\Order::STATUS_SUCCESS && System::_getInt('sms_send_order')) { $sp4ecb5c = @json_decode($spb656d1->contact_ext, true)['_mobile']; if ($sp4ecb5c) { $spb656d1->sendSms($sp4ecb5c); } } } else { } } else { Log::debug('Shop.Pay.shipOrder: .order_no:' . $spb656d1->order_no . ' already processed! #1'); } return FALSE; } private function showOrderResult($spbaac90, $spb656d1) { return self::renderResultPage($spbaac90, array('success' => true, 'msg' => $spb656d1->getSendMessage()), array('card_txt' => join('&#013;&#010;', $spb656d1->getCardsArray()), 'order' => $spb656d1, 'product' => $spb656d1->product)); } }
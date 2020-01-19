<?php
namespace App\Http\Controllers\Shop; use Illuminate\Database\Eloquent\Relations\Relation; use Illuminate\Http\Request; use App\Http\Controllers\Controller; use App\Library\Response; class Product extends Controller { function get(Request $sp7f7104) { $sp92ffbb = (int) $sp7f7104->post('category_id'); if (!$sp92ffbb) { return Response::forbidden('请选择商品分类'); } $sp45344f = \App\Category::where('id', $sp92ffbb)->first(); if (!$sp45344f) { return Response::forbidden('商品分类未找到'); } if ($sp45344f->password_open && $sp7f7104->post('password') !== $sp45344f->password) { return Response::fail('分类密码输入错误'); } $speaafa0 = \App\Product::where('category_id', $sp92ffbb)->where('enabled', 1)->orderBy('sort')->get(); foreach ($speaafa0 as $spfa410d) { $spfa410d->setForShop(); } return Response::success($speaafa0); } function verifyPassword(Request $sp7f7104) { $sp727288 = (int) $sp7f7104->post('product_id'); if (!$sp727288) { return Response::forbidden('请选择商品'); } $spfa410d = \App\Product::where('id', $sp727288)->first(); if (!$spfa410d) { return Response::forbidden('商品未找到'); } if ($spfa410d->password_open && $sp7f7104->post('password') !== $spfa410d->password) { return Response::fail('商品密码输入错误'); } return Response::success(); } }
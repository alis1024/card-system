<?php
$sp9922e0 = new Illuminate\Foundation\Application(realpath(__DIR__ . '/../')); $sp9922e0->singleton(Illuminate\Contracts\Http\Kernel::class, App\Http\Kernel::class); $sp9922e0->singleton(Illuminate\Contracts\Console\Kernel::class, App\Console\Kernel::class); $sp9922e0->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, App\Exceptions\Handler::class); return $sp9922e0;
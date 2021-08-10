<?php
/**
 * user: peter
 * Date：2021/8/10
 * Time: 11:57
 */

\think\Console::starting(function (\think\Console $console) {
    $console->addCommands([
        'package:config' => '\\GdPeter\\Tp6Addons\\command\\\SendConfig'
    ]);
});
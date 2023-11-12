<?php

namespace App\WebApplication\Plugins;

use Jenssegers\Blade\Blade as Original;
use PRipple;
use Std\CollaborativeFiberStd;
use Std\DependencyInjectionStandard;

class Blade extends Original implements DependencyInjectionStandard
{
    public function __construct()
    {
        $viewPath = PRipple::getArgument('VIEW_PATH_BLADE', PP_ROOT_PATH . '/.resources/views');
        $cachePath = PRipple::getArgument('PP_RUNTIME_PATH', PP_RUNTIME_PATH) . '/cache';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        if (!is_dir($viewPath)) {
            mkdir($viewPath, 0755, true);
        }
        parent::__construct($viewPath, $cachePath);
    }

    /**
     * @param CollaborativeFiberStd $collaborativeFiberStd
     * @return static|null
     */
    public static function createInjector(CollaborativeFiberStd $collaborativeFiberStd): static|null
    {
        $viewPath = PRipple::getArgument('VIEW_PATH_BLADE', PP_ROOT_PATH . '/.resources/views');
        $cachePath = PRipple::getArgument('PP_RUNTIME_PATH') . '/cache';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        if (!is_dir($viewPath)) {
            mkdir($viewPath, 0755, true);
        }
        return new static(
            $viewPath,
            $cachePath
        );
    }
}

<?php

namespace Std;

/**
 * 依赖映射
 * @package App\WebApplication
 */
interface DependencyInjectionStandard
{
    /**
     * 创建注入器
     * @param CollaborativeFiberStd $collaborativeFiberStd
     * @return DependencyInjectionStandard|null
     */
    public static function createInjector(CollaborativeFiberStd $collaborativeFiberStd): static|null;
}

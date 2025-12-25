<?php

namespace Swlib\Admin\Trait;

trait PermissionTrait
{

    /**
     * 允许那些角色访问
     * 如果为空，则允许所有角色访问
     * @var array
     */
    public array $roles = [] {
        get {
            return $this->roles;
        }
    }


    /**
     * 添加一个允许访问的角色
     * 如果没有添加任何角色，则允许所有角色访问
     * @param string|array $roles
     * @return $this
     */
    public function addPermission(string|array $roles): static
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        foreach ($roles as $role) {
            if (!in_array($role, $this->roles)) {
                $roles[] = $role;
            }
        }

        $this->roles = $roles;
        return $this;
    }

}
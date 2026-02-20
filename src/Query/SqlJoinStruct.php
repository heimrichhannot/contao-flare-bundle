<?php

namespace HeimrichHannot\FlareBundle\Query;

class SqlJoinStruct
{
    public function __construct(
        public string       $fromAlias,
        public JoinTypeEnum $joinType,
        public string       $table,
        public string       $joinAlias,
        public ?string      $condition = null,
    ) {}
}
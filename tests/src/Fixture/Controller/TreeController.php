<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use WaffleTests\Commons\OpenApi\Fixture\Folder;
use WaffleTests\Commons\OpenApi\Fixture\TreeNode;

final class TreeController
{
    public function echo(TreeNode $tree): TreeNode
    {
        return $tree;
    }

    public function folder(Folder $folder): Folder
    {
        return $folder;
    }
}

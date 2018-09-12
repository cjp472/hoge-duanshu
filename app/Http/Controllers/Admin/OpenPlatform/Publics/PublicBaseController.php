<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Http\Controllers\Admin\BaseController;

class PublicBaseController extends BaseController
{
	protected $type = 'public';

	public function __construct()
    {
        parent::__construct();
    }
}

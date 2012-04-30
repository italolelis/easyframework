<?php

/**
 * EasyFramework : Rapid Development Framework
 * Copyright 2011, EasyFramework (http://easyframework.net)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2011, EasyFramework (http://easyframework.net)
 * @since         EasyFramework v 1.5.4
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Represents an indexed collection of keys.
 * 
 * @package Easy.Collections
 */
interface ISortable {

    public function GetSortKey();
}

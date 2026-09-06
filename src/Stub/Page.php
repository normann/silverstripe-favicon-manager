<?php

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

if (!class_exists(Page::class)) {
    class Page extends SiteTree implements TestOnly
    {
    }
}

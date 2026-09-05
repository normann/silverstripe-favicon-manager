<?php

namespace Normann\FaviconManager\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;
use Normann\FaviconManager\Extension\FaviconSiteConfigExtension;

/**
 * NOTE: these tests are written against a standard SilverStripe testing environment
 * (SapphireTest + TestAssetStore) and have not been executed in this sandbox — there's
 * no PHP runtime available here to run them. Run `vendor/bin/phpunit` in a real
 * SilverStripe project before relying on them; adjust file-creation helpers below if
 * your SilverStripe version's asset test helpers differ.
 */
class FaviconSiteConfigExtensionTest extends SapphireTest
{
    protected static $required_extensions = [
        SiteConfig::class => [
            FaviconSiteConfigExtension::class,
        ],
    ];

    public function testUploadingZipPopulatesAllRelations(): void
    {
        $siteConfig = SiteConfig::current_site_config();
        $zip = $this->createZipFile(__DIR__ . '/Fixtures/favicon-package.zip', 'favicon-upload-1.zip');

        $siteConfig->FaviconArchiveZipID = $zip->ID;
        $siteConfig->write();

        $siteConfig = SiteConfig::get()->byID($siteConfig->ID);

        $this->assertTrue($siteConfig->Favicon()->exists(), 'Favicon (.ico) should be populated');
        $this->assertTrue($siteConfig->FaviconSVG()->exists(), 'FaviconSVG should be populated');
        $this->assertTrue($siteConfig->Favicon96x96()->exists(), 'Favicon96x96 should be populated');
        $this->assertTrue($siteConfig->AppleTouchIcon()->exists(), 'AppleTouchIcon should be populated');
        $this->assertTrue($siteConfig->WebAppManifest192x192()->exists(), 'WebAppManifest192x192 should be populated');
        $this->assertTrue($siteConfig->WebAppManifest512x512()->exists(), 'WebAppManifest512x512 should be populated');
        $this->assertTrue($siteConfig->Manifest()->exists(), 'Manifest should be populated');
    }

    public function testManifestIconPathsAreRewritten(): void
    {
        $siteConfig = SiteConfig::current_site_config();
        $zip = $this->createZipFile(__DIR__ . '/Fixtures/favicon-package.zip', 'favicon-upload-2.zip');

        $siteConfig->FaviconArchiveZipID = $zip->ID;
        $siteConfig->write();

        $siteConfig = SiteConfig::get()->byID($siteConfig->ID);
        $manifest = json_decode($siteConfig->Manifest()->getString(), true);

        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            // The fixture's src paths point at a placeholder location — after rewriting
            // they must point into this site's real favicons folder instead.
            $this->assertStringContainsString('/favicons/site' . $siteConfig->ID . '/', $icon['src']);
            $this->assertStringNotContainsString('/some/placeholder/path/', $icon['src']);
        }
    }

    public function testReuploadingZipReplacesPreviousFiles(): void
    {
        $siteConfig = SiteConfig::current_site_config();

        $zipV1 = $this->createZipFile(__DIR__ . '/Fixtures/favicon-package.zip', 'favicon-upload-3a.zip');
        $siteConfig->FaviconArchiveZipID = $zipV1->ID;
        $siteConfig->write();

        $originalFaviconID = $siteConfig->FaviconID;
        $this->assertNotEmpty($originalFaviconID);

        $zipV2 = $this->createZipFile(__DIR__ . '/Fixtures/favicon-package-updated.zip', 'favicon-upload-3b.zip');
        $siteConfig->FaviconArchiveZipID = $zipV2->ID;
        $siteConfig->write();

        $siteConfig = SiteConfig::get()->byID($siteConfig->ID);

        $this->assertNotEquals(
            $originalFaviconID,
            $siteConfig->FaviconID,
            'Re-uploading a ZIP should replace the previous Favicon file with a new one'
        );

        $originalFile = File::get()->byID($originalFaviconID);
        $this->assertTrue(
            $originalFile === null || !$originalFile->exists(),
            'The original Favicon file should be archived/removed after re-upload'
        );
    }

    public function testCacheKeyChangesWhenFaviconFilesChange(): void
    {
        $siteConfig = SiteConfig::current_site_config();
        $keyBefore = $siteConfig->getFaviconsCacheKey();

        $zip = $this->createZipFile(__DIR__ . '/Fixtures/favicon-package.zip', 'favicon-upload-4.zip');
        $siteConfig->FaviconArchiveZipID = $zip->ID;
        $siteConfig->write();

        $siteConfig = SiteConfig::get()->byID($siteConfig->ID);
        $keyAfter = $siteConfig->getFaviconsCacheKey();

        $this->assertNotEquals($keyBefore, $keyAfter, 'Cache key must change once favicon files are populated');
    }

    public function testCacheKeyChangesOnAnySiteConfigSave(): void
    {
        $siteConfig = SiteConfig::current_site_config();
        $keyBefore = $siteConfig->getFaviconsCacheKey();

        $siteConfig->Title = $siteConfig->Title . ' (updated)';
        $siteConfig->write();

        $keyAfter = $siteConfig->getFaviconsCacheKey();

        $this->assertNotEquals($keyBefore, $keyAfter, 'Cache key must change on any SiteConfig save (via LastEdited)');
    }

    /**
     * Copies a fixture ZIP into the test asset store and wraps it as a File record,
     * standing in for a CMS user uploading the ZIP through the UploadField.
     */
    private function createZipFile(string $fixturePath, string $targetFileName): File
    {
        $file = File::create();
        $file->setFromLocalFile($fixturePath, $targetFileName);
        $file->ParentID = 0;
        $file->write();
        $file->publishSingle();

        return $file;
    }
}

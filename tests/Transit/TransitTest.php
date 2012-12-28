<?php

namespace Transit;

use Transit\Test\TestCase;
use Transit\Transformer\Image\CropTransformer;
use Transit\Transformer\Image\ResizeTransformer;
use Transit\Transporter\Aws\S3Transporter;
use Transit\Validator\ImageValidator;
use \Exception;

class TransitTest extends TestCase {

	/**
	 * Initialize the Transit class.
	 */
	protected function setUp() {
		parent::setUp();

		$this->object = new Transit($this->data);
		$this->object->setDirectory(TEMP_DIR);
	}

	/**
	 * Test that findDestination() returns an absolute path to the target path.
	 * Checks for overwrites by incrementing file names.
	 */
	public function testFindDestination() {
		$this->assertEquals(TEMP_DIR . '/scott-pilgrim.jpg', $this->object->findDestination('scott-pilgrim.jpg', true));
		$this->assertEquals(TEMP_DIR . '/scott-pilgrim-1.jpg', $this->object->findDestination(new File($this->baseFile), false));
	}

	/**
	 * Test that importFromLocal() makes a copy of a file off the file system.
	 */
	public function testImportFromLocal() {
		$transit = new Transit($this->baseFile);
		$transit->setDirectory(TEMP_DIR);

		try {
			if ($transit->importFromLocal(false)) {
				$transit->getOriginalFile()->delete();

				$this->assertTrue(true);
			} else {
				$this->assertTrue(false);
			}
		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * Test that importFromRemote() downloads and makes a copy of a remote file.
	 */
	public function testImportFromRemote() {
		$transit = new Transit('http://getcomposer.org/img/logo-composer-transparent.png');
		$transit->setDirectory(TEMP_DIR);

		try {
			if ($transit->importFromRemote(false)) {
				$transit->getOriginalFile()->delete();

				$this->assertTrue(true);
			} else {
				$this->assertTrue(false);
			}
		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * Test that validation is triggered during upload.
	 * Validation should fail since the file is jpg, not png.
	 */
	public function testValidate() {
		$validator = new ImageValidator();
		$validator->addRule('ext', 'Invalid extension', array('png'));

		$this->object->setValidator($validator);

		try {
			$this->object->upload();

			$this->assertTrue(false);

		} catch (Exception $e) {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test that transforming will create new files based off the original.
	 */
	public function testTransform() {
		$this->object->addTransformer(new CropTransformer(array('width' => 100)));
		$this->object->addTransformer(new CropTransformer(array('height' => 100)));

		try {
			if ($this->object->upload()) {
				$files = $this->object->getTransformedFiles();

				$this->assertEquals(2, count($files));

				// Cleanup
				$this->object->getOriginalFile()->delete();

				foreach ($files as $file) {
					$file->delete();
				}
			}

			$this->assertTrue(true);

		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * Test that self transforming will alter the original file.
	 */
	public function testSelfTransform() {
		$this->object->addSelfTransformer(new ResizeTransformer(array('width' => 100, 'height' => 100, 'aspect' => false)));

		try {
			if ($this->object->upload()) {
				$files = $this->object->getTransformedFiles();
				$file = $this->object->getOriginalFile();

				$this->assertEquals(0, count($files));
				$this->assertEquals(100, $file->width());
				$this->assertEquals(100, $file->height());

				$file->delete();
			}

			$this->assertTrue(true);

		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * Test that transport moves files to a remote location and returns the new paths.
	 */
	public function testTransport() {
		$this->object->setTransporter(new S3Transporter(AWS_ACCESS, AWS_SECRET, array(
			'bucket' => S3_BUCKET,
			'region' => S3_REGION
		)));

		try {
			if ($this->object->upload()) {
				if ($files = $this->object->transport()) {
					$this->assertEquals(1, count($files));

					foreach ($files as $file) {
						$this->object->getTransporter()->delete($file);
					}
				}
			}
		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * Test that upload() copies over the temp file.
	 */
	public function testUpload() {
		try {
			if ($this->object->upload()) {
				$this->object->getOriginalFile()->delete();
			}

			$this->assertTrue(true);

		} catch (Exception $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * Test that exceptions are thrown if errors arise during upload.
	 */
	public function testUploadErrors() {
		$this->data['tmp_name'] = null;

		try {
			$transit = new Transit($this->data);
			$transit->upload();

			$this->assertTrue(false);

		} catch (Exception $e) {
			$this->assertTrue(true);
		}

		$this->data['tmp_name'] = $this->tempFile;
		$this->data['error'] = 3;

		try {
			$transit = new Transit($this->data);
			$transit->upload();

			$this->assertTrue(false);

		} catch (Exception $e) {
			$this->assertTrue(true);
		}
	}
}
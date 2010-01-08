<?php
/**
* Tests for verifying the package's structure in accordance with the packaging
* rules
*
* @package mpv
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
 * Collection of tests which are used to check the MOD's file structure
 *
 * @package		mpv
 * @subpackage	tests
 */
class mpv_tests_packaging
{
	/**
	 * mpv (validator) object
	 *
	 * @access	private
	 * @var		object
	 */
	private $validator;

	/**
	 * Terminate the testing process
	 *
	 * @access	private
	 * @var		bool
	 */
	private $terminate;

	/**
	 * Array containing the names of the tests which failed
	 *
	 * @access	private
	 * @var		array
	 */
	private $failed_tests;

	/**
	 * Array of "unwanted files", all strtolowered
	 *
	 * @access	private
	 * @var		array
	 */
	private $unwanted_files = array('__macosx', '.ds_store', 'thumbs.db', '.svn');

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	object		mpv object
	 * @return	void
	 */
	public function __construct(mpv $validator)
	{
		$this->validator = $validator;
		$this->terminate = false;
		$this->failed_tests = array();
	}

	/**
	 * Run the tests in this collection
	 *
	 * @access 	public
	 * @return 	void
	 */
	public function run()
	{
		$test_methods = get_class_methods($this);
		foreach ($test_methods as $method)
		{
			if (substr($method, 0, 5) == 'test_')
			{
				if (!$this->$method() || $this->terminate)
				{
					$this->failed_tests[] = substr($method, 5);
				}

				if ($this->terminate)
				{
					return;
				}
			}
		}
	}

	/**
	 * Test if the there is more as 1 xsl file
	 *
	 * @access private
	 * @return bool
	 */
	private function test_xsl()
	{
		if (sizeof($this->validator->xsl_files) == 0)
		{
			$this->push_error(mpv::ERROR_FAIL, 'NO_XSL_FILE');
			return false;
		}

		return true;
	}

	/**
	 * Checks to see if the required license.txt exists
	 *
	 * @access private
	 * @return bool
	 */
	private function test_license()
	{
		foreach ($this->validator->package_files as $filename)
		{
			if (strtolower(basename($filename)) == 'license.txt')
			{
				return true;
			}
		}
		$this->push_error(mpv::ERROR_FAIL, 'NO_LICENSE');
		return false;
	}

	/**
	 * Test to see if prosilver.xml or english.xml exisits.
	 *
	 * @return bool
	 * @access private
	 */
	private function test_prosilver_english()
	{
		$return = true;
		foreach ($this->validator->package_files as $filename)
		{
			$file = strtolower(basename($filename));

			if ($file == 'prosilver.xml' || (strpos($file, 'prosilver') !== false && strpos($file, '.xml') !== false))
			{
				$this->push_error(mpv::ERROR_FAIL, 'PROSILVER_NO_MAIN_MODX', null, array($filename));
				$return = false;
			}

			if ($file == 'en.xml' || (strpos($file, 'english') !== false && strpos($file, '.xml') !== false))
			{
				$this->push_error(mpv::ERROR_FAIL, 'ENGLISH_NO_MAIN_MODX', null, array($filename));
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * Checks to see if there are bad files from svn or the OS
	 *
	 * @access private
	 * @return bool
	 */
	private function test_unwanted()
	{
		/**
		 * @TODO: Does this work? I never saw a notice regarding it?
		 */
		// precache regexp for efficiency
		$regexp = '#(^|.*/)(' . implode('|', array_map('preg_quote', $this->unwanted_files)) . ')(?:/|$)#i';

		$unwanted_files = array();
		foreach ($this->validator->package_files as $filename)
		{
			if (preg_match($regexp, $filename, $matches))
			{
				// don't add files multiple times
				if (isset($unwanted_files[$matches[1] . $matches[2]]))
				{
					continue;
				}

				// add unwanted file, use array keys for efficiency
				$unwanted_files[$matches[1] . $matches[2]] = true;

				// if there is no dir, it's the root
				if ($matches[1] === '')
				{
					$matches[1] = './';
				}

				// push notice
				$this->push_error(mpv::ERROR_NOTICE, 'UNWANTED_FILE', null, array($matches[1], $matches[2]));
			}
		}

		return sizeof($unwanted_files);
	}

	/**
	 * Wrapper around $this->validator->push_error
	 *
	 * @access	private
	 * @param	int			Error type
	 * @param	string		Message
	 * @param	string		Filename of the file causing the error
	 * @param	mixed		Optional array of sprintf() values, or a non-array for passing one single value
	 * @return	void
	 */
	private function push_error($type, $message, $filename = null, $sprintf_args = null)
	{
		$this->validator->push_error($type, $message, $filename, $sprintf_args);
	}

	/**
	 * Displays a inline code block with the line of wrong code.
	 *
	 * @access	private
	 * @param	int		Error type
	 * @param	string	Message
	 * @param	string	String to use in strpos
	 * @param	string	String to use in preg_match
	 * @param array  array that contains words that should not be in the line
	 * @return	bool
	 */
	private function display_line_code($type, $message, $strpos, $preg_match = false, $ignore_in_line = array())
	{
		$found = false;

		$in_comment = false;

		if (!is_array($ignore_in_line))
		{
		  if (is_string($ignore_in_line))
		  {
		    $ignore_in_line = array(0 => $ignore_in_line);
			}
			else
			{
			  $ignore_in_line = array();
			}
		}

		foreach ($this->file_contents_file as $line => $content)
		{
			$content_new = $content;
			$loc = strpos($content, '*/');

			if ($in_comment && $loc === false)
			{
				$content_new = '';
			}
			else if ($in_comment && $loc !== false)
			{
				// Need to replace everything till */
				$total = strlen($content_new);
				$negative = $total - $loc;
				$total = $total - $negative;

				$content_new = substr($content_new, ($loc + 2));
				$in_comment = false;
			}
			else if(!$in_comment && strpos($content, '/*') !== false)
			{
				if ($loc !== false) // Used as inline
				{
					$content_new = preg_replace('#/\*(.*)\*/#si', '', $content_new);
				}
				else
				{
					$in_comment = true;

					$content_new = substr($content_new, 0, strpos($content, '/*'));
				}
			}
			$loc = strpos($content_new, '//');

			if ($loc !== false)
			{
				$content_new = substr($content_new, 0, $loc + 2);
			}

			foreach ($ignore_in_line as $value)
			{
			  if (strpos($content_new, $value) !== false)
			  {
			    $content_new = '';
				}
			}

			// Yes, $content_new in if, and $content in code.
			// This is because we want comments still being displayed ;)
			if (!$preg_match && strpos($content_new, $strpos) !== false)
			{
				$this->push_error($type, $message, array((string) ($line + 1) , '[code]' . trim($content) . '[/code]'));
				$found = true;
			}
			else if ($preg_match && preg_match($preg_match, $content))
			{
				$this->push_error($type, $message, array((string) ($line + 1) , '[code]' . trim($content) . '[/code]'));
				$found = true;
			}
		}

		return $found;
	}

	/**
	 * Terminate all further testing; used if failing one test could result in other tests malfunctioning
	 *
	 * @access	private
	 * @return	bool
	 */
	private function terminate()
	{
		$this->terminate = true;
		$this->push_error(mpv::ERROR_WARNING, 'TESTING_TERMINATED');

		return false;
	}

	/**
	 * Check whether a given test failed
	 *
	 * @access	private
	 * @param	string		Test name
	 * @return	bool
	 */
	private function failed_test($test_name)
	{
		return in_array($test_name, $this->failed_tests);
	}

	/**
	 * Returns a array with failed tests
	 *
	 * @access	public
	 * @return	array
	 */
	 public function return_failed_tests()
	 {
		return $this->failed_tests;
	 }
}
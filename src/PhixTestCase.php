<?php
/**
 * Phix
 *
 * LICENSE
 *
 * This source file is subject to the BSD license that is available
 * through the world-wide-web at this URL:
 * http://opensource.org/licenses/bsd-license.php
 *
 * @package   Phix
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */

/**
 * @package   Phix
 * @author    Jan Sorgalla
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   http://opensource.org/licenses/bsd-license.php The BSD License
 */
class PhixTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * The Phix instance.
     * @var Phix
     */
    protected $_phix;

    /**
     * Set Phix instance.
     *
     * @param Phix $phix
     * @return void
     */
    public function setPhix(Phix $phix)
    {
        $this->_phix = $phix;
    }

    /**
     * Get Phix instance.
     *
     * @return Phix
     */
    public function getPhix()
    {
        if (null === $this->_phix) {
            $this->_phix = new Phix();
        }

        return $this->_phix;
    }

    /**
     * Run Phix.
     *
     * @param  string|null $url
     * @param  string|null $requestMethod
     * @return void
     */
    public function runPhix($url = null, $requestMethod = null)
    {
        $phix = $this->getPhix();

        $phix->reset();

        if (null !== $requestMethod) {
            $phix->requestMethod($requestMethod);
        }

        if (null !== $url) {
            $phix
                ->requestUri($url)
                ->pathInfo(null);
        }

        $phix
            ->autoFlush(false)
            ->run();
    }

    /**
     * Assert against XPath selection.
     *
     * @param  string $path XPath path
     * @param  string $message
     * @return void
     */
    public function assertXpath($path, $message = '')
    {
        $this->addToAssertionCount(1);

        $output = $this->getPhix()->output();
        $isXml = '<' . '?xml' == substr(trim($output), 0, 5);

        libxml_use_internal_errors(true);
        $domDoc = new DOMDocument;
        if ($isXml) {
            $success = $domDoc->loadXML($output);
        } else {
            $success = $domDoc->loadHTML($output);
        }
        libxml_use_internal_errors(false);

        if (!$success) {
            throw new Exception(sprintf('Error parsing document (type == %s)', $isXml ? 'xml' : 'html'));
        }

        $xpath = new DOMXPath($domDoc);
        $nodeList = $xpath->query($path);

        if (0 == $nodeList->length) {
            $failure = sprintf('Failed asserting node DENOTED BY %s EXISTS', $path);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert against XPath selection; node should contain content.
     *
     * @param  string $path XPath path
     * @param  string $match content that should be contained in matched nodes
     * @param  string $message
     * @return void
     */
    public function assertXpathContentContains($path, $match, $message = '')
    {
        $this->addToAssertionCount(1);

        $output = $this->getPhix()->output();
        $isXml = '<' . '?xml' == substr(trim($output), 0, 5);

        libxml_use_internal_errors(true);
        $domDoc = new DOMDocument;
        if ($isXml) {
            $success = $domDoc->loadXML($output);
        } else {
            $success = $domDoc->loadHTML($output);
        }
        libxml_use_internal_errors(false);

        if (!$success) {
            throw new Exception(sprintf('Error parsing document (type == %s)', $isXml ? 'xml' : 'html'));
        }

        $xpath = new DOMXPath($domDoc);
        $nodeList = $xpath->query($path);

        $found = false;

        if (0 != $nodeList->length) {
            for ($i = 0; $i < $nodeList->length; $i++) {
                $node = $nodeList->item($i);

                $doc     = $node->ownerDocument;
                $content = $doc->saveXML($node);
                $tag     = $node->nodeName;
                $regex   = '|</?' . $tag . '[^>]*>|';
                $content = preg_replace($regex, '', $content);

                if (strstr($content, $match)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $failure = sprintf('Failed asserting node denoted by %s CONTAINS content "%s"', $path, $match);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert that response is a redirect.
     *
     * @param  string $message
     * @return void
     */
    public function assertRedirect($message = '')
    {
        $this->addToAssertionCount(1);

        $status = $this->getPhix()->status();
        if (!(300 <= $status) && (307 >= $status)) {
            $failure = 'Failed asserting response is a redirect';
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert that response redirects to given URL.
     *
     * @param  string $url
     * @param  string $message
     * @return void
     */
    public function assertRedirectTo($url, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;

        $status = $this->getPhix()->status();
        if (300 <= $status && 307 >= $status) {
            foreach ($this->getPhix()->headers() as $h) {
                if (stripos($h, 'Location') === 0) {
                    $contents = str_ireplace('Location: ', '', $h);
                    if ($contents == $url) {
                        $found = true;
                        break;
                    }
                }
            }
        }

        if (!$found) {
            $failure = sprintf('Failed asserting response redirects to "%s"', $url);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response code.
     *
     * @param  int $code
     * @param  string $message
     * @return void
     */
    public function assertStatus($status, $message = '')
    {
        $this->addToAssertionCount(1);

        if ($status != $this->getPhix()->status()) {
            $failure = sprintf('Failed asserting status code "%s"', $status);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response header exists.
     *
     * @param  string $header
     * @param  string $message
     * @return void
     */
    public function assertHeader($header, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;
        foreach ($this->getPhix()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $failure = sprintf('Failed asserting response header "%s" found', $header);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response header exists and contains the given string.
     *
     * @param  string $header
     * @param  string $match
     * @param  string $message
     * @return void
     */
    public function assertHeaderContains($header, $match, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;
        foreach ($this->getPhix()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $contents = str_ireplace($header . ': ', '', $h);
                if (strstr($contents, $match)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $failure = sprintf('Failed asserting response header "%s" exists and contains "%s"', $header, $match);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response header exists and matches the given pattern.
     *
     * @param  string $header
     * @param  string $pattern
     * @param  string $message
     * @return void
     */
    public function assertHeaderRegex($header, $pattern, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;
        foreach ($this->getPhix()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $contents = str_ireplace($header . ': ', '', $h);
                if (preg_match($pattern, $contents)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $failure = sprintf('Failed asserting response header "%s" exists and matches regex "%s"', $header, $pattern);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }
}

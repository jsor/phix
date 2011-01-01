<?php
/**
 * Phix
 *
 * LICENSE
 *
 * This source file is subject to the BSD license that is available
 * through the world-wide-web at this URL:
 * https://github.com/jsor/phix/blob/master/LICENSE
 *
 * @package   Phix
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   https://github.com/jsor/phix/blob/master/LICENSE The BSD License
 */

namespace Phix;

/**
 * @package   Phix
 * @author    Jan Sorgalla
 * @copyright Copyright (c) 2010-Present Jan Sorgalla
 * @license   https://github.com/jsor/phix/blob/master/LICENSE The BSD License
 */
class AppTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * The App instance.
     * @var App
     */
    protected $_app;

    /**
     * Set App instance.
     *
     * @param App $app
     * @return void
     */
    public function setApp(App $app)
    {
        $this->_app = $app;
    }

    /**
     * Get App instance.
     *
     * @return \Phix\App
     */
    public function getApp()
    {
        if (null === $this->_app) {
            $this->_app = new App();
        }

        return $this->_app;
    }

    /**
     * Run App.
     *
     * @param  string|null $url
     * @param  string|null $requestMethod
     * @return void
     */
    public function runApp($url = null, $requestMethod = null)
    {
        $app = $this->getApp();

        $app->reset();

        if (null !== $requestMethod) {
            $app->requestMethod($requestMethod);
        }

        if (null !== $url) {
            $app
                ->requestUri($url)
                ->pathInfo(null);
        }

        $app
            ->env(App::ENV_TESTING)
            ->autoFlush(false)
            ->run();
    }

    /**
     * Query a XPath selection.
     *
     * @param  string $output
     * @param  string $path
     * @return \DOMNodeList
     */
    protected function _queryXPath($output, $path)
    {
        $isXml = '<' . '?xml' == substr(trim($output), 0, 5);

        libxml_use_internal_errors(true);
        $domDoc = new \DOMDocument;
        if ($isXml) {
            $success = $domDoc->loadXML($output);
        } else {
            $success = $domDoc->loadHTML($output);
        }
        libxml_use_internal_errors(false);

        if (!$success) {
            throw new \Exception(sprintf('Error parsing document (type == %s)', $isXml ? 'xml' : 'html'));
        }

        $xpath = new \DOMXPath($domDoc);
        return $xpath->query($path);
    }

    /**
     * Get node content, minus node markup tags
     *
     * @param DOMNode $node
     * @return string
     */
    protected function _getNodeContent(\DOMNode $node)
    {
        if ($node instanceof \DOMAttr) {
            return $node->value;
        } else {
            $doc = $node->ownerDocument;
            $content = $doc->saveXML($node);
            $tag = $node->nodeName;
            $regex = '|</?' . $tag . '[^>]*>|';
            return preg_replace($regex, '', $content);
        }
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

        $nodeList = $this->_queryXPath($this->getApp()->output(), $path);

        if (0 == $nodeList->length) {
            $failure = sprintf('Failed asserting node DENOTED BY %s EXISTS', $path);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert against XPath selection.
     *
     * @param  string $path XPath path
     * @param  string $message
     * @return void
     */
    public function assertNotXpath($path, $message = '')
    {
        $this->addToAssertionCount(1);

        $nodeList = $this->_queryXPath($this->getApp()->output(), $path);

        if (0 < $nodeList->length) {
            $failure = sprintf('Failed asserting node DENOTED BY %s DOES NOT EXIST', $path);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Query a XPath selectiona and check if node contains content.
     *
     * @param  string $path XPath path
     * @param  string $match content that should be contained in matched nodes
     * @return boolean
     */
    protected function _checkXpathContentContains($path, $match)
    {
        $nodeList = $this->_queryXPath($this->getApp()->output(), $path);

        if (0 != $nodeList->length) {
            for ($i = 0; $i < $nodeList->length; $i++) {
                $content = $this->_getNodeContent($nodeList->item($i));

                if (strstr($content, $match)) {
                    return true;
                }
            }
        }

        return false;
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

        if (!$this->_checkXpathContentContains($path, $match)) {
            $failure = sprintf(
                'Failed asserting node denoted by %s CONTAINS content "%s"',
                $path,
                $match
            );

            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert against XPath selection; node should NOT contain content.
     *
     * @param  string $path XPath path
     * @param  string $match content that should NOT be contained in matched nodes
     * @param  string $message
     * @return void
     */
    public function assertNotXpathContentContains($path, $match, $message = '')
    {
        $this->addToAssertionCount(1);

        if ($this->_checkXpathContentContains($path, $match)) {
            $failure = sprintf(
                'Failed asserting node denoted by %s DOES NOT CONTAIN content "%s"',
                $path,
                $match
            );

            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
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

        $status = $this->getApp()->status();
        if (!(300 <= $status && 307 >= $status)) {
            $failure = 'Failed asserting response is a redirect';
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert that response is NOT a redirect.
     *
     * @param  string $message
     * @return void
     */
    public function assertNotRedirect($message = '')
    {
        $this->addToAssertionCount(1);

        $status = $this->getApp()->status();
        if (300 <= $status && 307 >= $status) {
            $failure = 'Failed asserting response is NOT a redirect';
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Check that response redirects to given URL.
     *
     * @param  string $url
     * @return boolean
     */
    protected function _checkRedirectTo($url)
    {
        $status = $this->getApp()->status();
        if (300 <= $status && 307 >= $status) {
            foreach ($this->getApp()->headers() as $h) {
                if (stripos($h, 'Location') === 0) {
                    $contents = str_ireplace('Location: ', '', $h);
                    if ($contents == $url) {
                        return true;
                    }
                }
            }
        }

        return false;
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

        if (!$this->_checkRedirectTo($url)) {
            $failure = sprintf('Failed asserting response redirects to "%s"', $url);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert that response does not redirect to given URL.
     *
     * @param  string $url
     * @param  string $message
     * @return void
     */
    public function assertNotRedirectTo($url, $message = '')
    {
        $this->addToAssertionCount(1);

        if ($this->_checkRedirectTo($url)) {
            $failure = sprintf('Failed asserting response DOES NOT redirect to "%s"', $url);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert status code.
     *
     * @param  int $code
     * @param  string $message
     * @return void
     */
    public function assertStatus($status, $message = '')
    {
        $this->addToAssertionCount(1);

        if ($status != $this->getApp()->status()) {
            $failure = sprintf('Failed asserting status code "%s"', $status);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert status code.
     *
     * @param  int $code
     * @param  string $message
     * @return void
     */
    public function assertNotStatus($status, $message = '')
    {
        $this->addToAssertionCount(1);

        if ($status == $this->getApp()->status()) {
            $failure = sprintf('Failed asserting status code IS NOT "%s"', $status);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
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
        foreach ($this->getApp()->headers() as $h) {
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
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response header does not exist.
     *
     * @param  string $header
     * @param  string $message
     * @return void
     */
    public function assertNotHeader($header, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;
        foreach ($this->getApp()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $found = true;
                break;
            }
        }

        if ($found) {
            $failure = sprintf('Failed asserting response header "%s" WAS NOT found', $header);
            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
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
        foreach ($this->getApp()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $contents = str_ireplace($header . ': ', '', $h);
                if (strstr($contents, $match)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $failure = sprintf(
                'Failed asserting response header "%s" exists and contains "%s"',
                $header,
                $match
            );

            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response header does not exist and/or does not contain the given string.
     *
     * @param  string $header
     * @param  string $match
     * @param  string $message
     * @return void
     */
    public function assertNotHeaderContains($header, $match, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;
        foreach ($this->getApp()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $contents = str_ireplace($header . ': ', '', $h);
                if (strstr($contents, $match)) {
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            $failure = sprintf(
                'Failed asserting response header "%s" DOES NOT CONTAIN "%s"',
                $header,
                $match
            );

            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
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
        foreach ($this->getApp()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $contents = str_ireplace($header . ': ', '', $h);
                if (preg_match($pattern, $contents)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $failure = sprintf(
                'Failed asserting response header "%s" exists and matches regex "%s"',
                $header,
                $pattern
            );

            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }

    /**
     * Assert response header does not exist and/or does not match the given pattern.
     *
     * @param  string $header
     * @param  string $pattern
     * @param  string $message
     * @return void
     */
    public function assertNotHeaderRegex($header, $pattern, $message = '')
    {
        $this->addToAssertionCount(1);

        $found = false;
        foreach ($this->getApp()->headers() as $h) {
            if (stripos($h, $header) === 0) {
                $contents = str_ireplace($header . ': ', '', $h);
                if (preg_match($pattern, $contents)) {
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            $failure = sprintf(
                'Failed asserting response header "%s" DOES NOT MATCH regex "%s"',
                $header,
                $pattern
            );

            if (!empty($message)) {
                $failure = $message . "\n" . $failure;
            }
            throw new \PHPUnit_Framework_ExpectationFailedException($failure);
        }
    }
}

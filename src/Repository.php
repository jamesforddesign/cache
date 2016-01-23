<?php
namespace Nodes\Cache;

use Illuminate\Support\Facades\Cache as IlluminateCache;

/**
 * Class Repository
 *
 * @package Nodes\Cache
 */
class Repository
{
    /**
     * Cache config
     * @var array
     */
    protected $config;

    /**
     * Cache groups
     * @var array
     */
    protected $cacheGroups = [];

    /**
     * Constructor
     *
     * @access public
     * @param  array $config
     */
    public function __construct(array $config)
    {
        // Setup cache groups
        $cacheGroups = array_pull($config, 'groups');
        $this->setupCacheGroups($cacheGroups);

        // Set cache config
        $this->config = $config;
    }

    /**
     * Retrieve cache
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @param  string $cacheGroup
     * @param  array  $params
     * @param  array  $tags
     * @return mixed|null
     */
    public function get($cacheGroup, array $params = [], $tags = ['default'])
    {
        // Make sure caching is enabled
        if (!$this->isCachingEnabled()) {
            return null;
        }

        // Retrieve cache group settings
        $cacheGroup = $this->getCacheGroup($cacheGroup);
        if (empty($cacheGroup) || (empty($cacheGroup['active']) || empty($cacheGroup['key']))) {
            return null;
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($cacheGroup['key'], $params);

        // Make sure we have an array of tags
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        return IlluminateCache::tags($tags)->get($cacheKey);
    }

    /**
     * Write to cache
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @param  string $cacheGroup
     * @param  array $params
     * @param  mixed $data
     * @param  array $tags
     * @return boolean
     */
    public function put($cacheGroup, array $params = [], $data, $tags = ['default'])
    {
        // Make sure caching is enabled
        if (!$this->isCachingEnabled()) {
            return null;
        }

        // Retrieve cache group settings
        $cacheGroup = $this->getCacheGroup($cacheGroup);
        if (empty($cacheGroup) || (empty($cacheGroup['active']) || empty($cacheGroup['key']))) {
            return null;
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($cacheGroup['key'], $params);

        // Make sure we have an array of tags
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        return IlluminateCache::tags($tags)->put($cacheKey, $data, $cacheGroup['lifetime'] ?: $this->config['lifetime']);
    }

    /**
     * Delete cache
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @param  string $cacheGroup
     * @param  array  $params
     * @param  array  $tags
     * @return boolean
     */
    public function forget($cacheGroup, array $params = [], $tags = ['default'])
    {
        // Make sure caching is enabled
        if (!$this->isCachingEnabled()) {
            return null;
        }

        // Retrieve cache group settings
        $cacheGroup = $this->getCacheGroup($cacheGroup);
        if (empty($cacheGroup) || (empty($cacheGroup['active']) || empty($cacheGroup['key']))) {
            return null;
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($cacheGroup['key'], $params);

        // Make sure we have an array of tags
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        return IlluminateCache::tags($tags)->forget($cacheKey);
    }

    /**
     * Flush cache by list of tags
     *
     * @author Casper Rasmussen <cr@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @param  array|string $tags
     * @return boolean
     */
    public function flush($tags = ['default'])
    {
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        return IlluminateCache::tags($tags)->flush();
    }

    /**
     * Flush entire cache
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @return boolean
     */
    public function wipe()
    {
        return IlluminateCache::flush();
    }

    /**
     * Retrieve cache group
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @param  string $cacheGroup
     * @return array|null
     */
    public function getCacheGroup($cacheGroup)
    {
        return !empty($this->cacheGroups[$cacheGroup]) ? $this->cacheGroups[$cacheGroup] : null;
    }

    /**
     * Retrieve available cache groups
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @return array
     */
    public function getCacheGroups()
    {
        return $this->cacheGroups;
    }

    /**
     * Retrieve cache group by group and key
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access public
     * @param  string $group
     * @param  string $key
     * @return array|null
     */
    public function getCacheGroupByGroupAndKey($group, $key)
    {
        $cacheGroupName = $group . '.' . $key;
        return $this->getCacheGroup($cacheGroupName);
    }

    /**
     * Generate cache key
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access private
     * @param  string      $key
     * @param  string|array $params
     * @return string
     */
    private function generateCacheKey($key, $params)
    {
        // If our parameters is an array
        // we need to do some work to it.
        if (is_array($params)) {
            // Sort array and maintain index
            asort($params);

            // Convert array to a query string
            $params = http_build_query($params);
        }

        return $key . '|' . $params;
    }

    /**
     * Setup cache groups
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   25-09-2015
     *
     * @access private
     * @param  array $groups
     * @return void
     */
    private function setupCacheGroups(array $groups)
    {
        foreach ($groups as $group => $keys) {
            // If $keys is not an array, something is wrong
            // with the structure in the config file
            // and we'll just skip it.
            if (!is_array($keys)) {
                continue;
            }

            foreach ($keys as $key => $settings) {
                // Generate group name
                $groupName = $group . '.' . $key;

                // Make sure tag name doesn't already exists.
                // If it does, we'll keep the first one adde
                // and skip all other ones.
                if (array_key_exists($groupName, $this->cacheGroups)) {
                    continue;
                }

                // Add tag to our tags container
                $this->cacheGroups[$groupName] = $settings;
            }
        }
    }

    /**
     * Check if caching is enabled
     *
     * @author Morten Rugaard <moru@nodes.dk>
     * @date   16-10-2015
     *
     * @access protected
     * @return boolean
     */
    protected function isCachingEnabled()
    {
        return (bool) $this->config['enabled'];
    }
}
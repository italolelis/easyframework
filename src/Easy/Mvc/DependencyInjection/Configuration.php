<?php

// Copyright (c) Lellys Informática. All rights reserved. See License.txt in the project root for license information.

namespace Easy\Mvc\DependencyInjection;

use RuntimeException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder as TreeBuilder2;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * FrameworkExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration implements ConfigurationInterface
{

    private $debug;

    /**
     * Constructor
     *
     * @param Boolean $debug Whether to use the debug mode
     */
    public function __construct($debug)
    {
        $this->debug = (Boolean) $debug;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder2 The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('framework');

        $rootNode
                ->children()
                ->scalarNode('charset')
                ->defaultNull()
                ->beforeNormalization()
                ->ifTrue(function($v) {
                            return null !== $v;
                        })
                ->then(function($v) {
                            $message = 'The charset setting is deprecated. Just remove it from your configuration file.';

                            if ('UTF-8' !== $v) {
                                $message .= sprintf(' You need to define a getCharset() method in your Application Kernel class that returns "%s".', $v);
                            }

                            throw new RuntimeException($message);
                        })
                ->end()
                ->end()
                ->scalarNode('trust_proxy_headers')->defaultFalse()->end()
                ->scalarNode('secret')->defaultNull()->end()
                ->scalarNode('default_locale')->defaultValue('en')->end()
                ->scalarNode('default_timezone')->defaultValue('America/Recife')->end()
                ->end();

        $this->addSessionSection($rootNode);
        $this->addTemplatingSection($rootNode);
        return $treeBuilder;
    }

    private function addSessionSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
                ->children()
                ->arrayNode('session')
                ->info('session configuration')
                ->canBeUnset()
                ->children()
                ->booleanNode('auto_start')
                ->info('DEPRECATED! Session starts on demand')
                ->defaultNull()
                ->beforeNormalization()
                ->ifTrue(function($v) {
                            return null !== $v;
                        })
                ->then(function($v) {
                            throw new \RuntimeException('The auto_start setting is deprecated. Just remove it from your configuration file.');
                        })
                ->end()
                ->end()
                ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
                ->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
                ->scalarNode('name')->end()
                ->scalarNode('cookie_lifetime')->end()
                ->scalarNode('cookie_path')->end()
                ->scalarNode('cookie_domain')->end()
                ->booleanNode('cookie_secure')->end()
                ->booleanNode('cookie_httponly')->end()
                ->scalarNode('gc_divisor')->end()
                ->scalarNode('gc_probability')->end()
                ->scalarNode('gc_maxlifetime')->end()
                ->scalarNode('save_path')->defaultValue('%kernel.cache_dir%/sessions')->end()
                ->scalarNode('lifetime')->info('DEPRECATED! Please use: cookie_lifetime')->end()
                ->scalarNode('path')->info('DEPRECATED! Please use: cookie_path')->end()
                ->scalarNode('domain')->info('DEPRECATED! Please use: cookie_domain')->end()
                ->booleanNode('secure')->info('DEPRECATED! Please use: cookie_secure')->end()
                ->booleanNode('httponly')->info('DEPRECATED! Please use: cookie_httponly')->end()
                ->end()
                ->end()
                ->end()
        ;
    }

    private function addTemplatingSection(ArrayNodeDefinition $rootNode)
    {
        $organizeUrls = function($urls) {
                    $urls += array(
                        'http' => array(),
                        'ssl' => array(),
                    );

                    foreach ($urls as $i => $url) {
                        if (is_integer($i)) {
                            if (0 === strpos($url, 'https://') || 0 === strpos($url, '//')) {
                                $urls['http'][] = $urls['ssl'][] = $url;
                            } else {
                                $urls['http'][] = $url;
                            }
                            unset($urls[$i]);
                        }
                    }

                    return $urls;
                };

        $rootNode
                ->children()
                ->arrayNode('templating')
                ->info('templating configuration')
                ->canBeUnset()
                ->children()
                ->scalarNode('assets_version')->defaultValue(null)->end()
                ->scalarNode('assets_version_format')->defaultValue('%%s?%%s')->end()
                ->scalarNode('hinclude_default_template')->defaultNull()->end()
                ->arrayNode('form')
                ->addDefaultsIfNotSet()
                ->fixXmlConfig('resource')
                ->children()
                ->arrayNode('resources')
                ->addDefaultChildrenIfNoneSet()
                ->prototype('scalar')->defaultValue('FrameworkBundle:Form')->end()
                ->validate()
                ->ifTrue(function($v) {
                            return !in_array('FrameworkBundle:Form', $v);
                        })
                ->then(function($v) {
                            return array_merge(array('FrameworkBundle:Form'), $v);
                        })
                ->end()
                ->end()
                ->end()
                ->end()
                ->end()
                ->fixXmlConfig('assets_base_url')
                ->children()
                ->arrayNode('assets_base_urls')
                ->performNoDeepMerging()
                ->addDefaultsIfNotSet()
                ->beforeNormalization()
                ->ifTrue(function($v) {
                            return !is_array($v);
                        })
                ->then(function($v) {
                            return array($v);
                        })
                ->end()
                ->beforeNormalization()
                ->always()
                ->then($organizeUrls)
                ->end()
                ->children()
                ->arrayNode('http')
                ->prototype('scalar')->end()
                ->end()
                ->arrayNode('ssl')
                ->prototype('scalar')->end()
                ->end()
                ->end()
                ->end()
                ->scalarNode('cache')->end()
                ->end()
                ->fixXmlConfig('engine')
                ->children()
                ->arrayNode('engines')
                ->example(array('twig'))
                ->isRequired()
                ->requiresAtLeastOneElement()
                ->beforeNormalization()
                ->ifTrue(function($v) {
                            return !is_array($v);
                        })
                ->then(function($v) {
                            return array($v);
                        })
                ->end()
                ->prototype('scalar')->end()
                ->end()
                ->end()
                ->fixXmlConfig('loader')
                ->children()
                ->arrayNode('loaders')
                ->beforeNormalization()
                ->ifTrue(function($v) {
                            return !is_array($v);
                        })
                ->then(function($v) {
                            return array($v);
                        })
                ->end()
                ->prototype('scalar')->end()
                ->end()
                ->end()
                ->fixXmlConfig('package')
                ->children()
                ->arrayNode('packages')
                ->useAttributeAsKey('name')
                ->prototype('array')
                ->fixXmlConfig('base_url')
                ->children()
                ->scalarNode('version')->defaultNull()->end()
                ->scalarNode('version_format')->defaultValue('%%s?%%s')->end()
                ->arrayNode('base_urls')
                ->performNoDeepMerging()
                ->addDefaultsIfNotSet()
                ->beforeNormalization()
                ->ifTrue(function($v) {
                            return !is_array($v);
                        })
                ->then(function($v) {
                            return array($v);
                        })
                ->end()
                ->beforeNormalization()
                ->always()
                ->then($organizeUrls)
                ->end()
                ->children()
                ->arrayNode('http')
                ->prototype('scalar')->end()
                ->end()
                ->arrayNode('ssl')
                ->prototype('scalar')->end()
                ->end()
                ->end()
                ->end()
                ->end()
                ->end()
                ->end()
                ->end()
                ->end()
                ->end()
        ;
    }

}

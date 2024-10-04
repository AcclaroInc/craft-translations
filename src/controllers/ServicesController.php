<?php
/**
 * Translations for Craft plugin for Craft CMS 5.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\controllers;

use craft\web\Controller;

class ServicesController extends Controller
{
    public function actionIndex()
    {
            $variables = array();
            $servicesData = [
                [
                    'title' => 'Professional Translation',
                    'description' => 'Elevate your content with our professional human translation services.',
                    'message' => 'Our team of expert linguists ensures your message resonates in any language. Perfect for critical content where accuracy and cultural relevance are paramount.',
                    'ctaText' => 'Get a Quote',
                    'ctaUrl' => '#',
                    'keyBenefits' => [
                        'Industry-specific expertise',
                        'Cultural nuance and context preservation',
                        'Consistent brand voice across languages',
                    ],
                ],
                [
                    'title' => 'Quality Review',
                    'description' => 'Ensure flawless translations with our comprehensive quality review.',
                    'message' => 'Our rigorous quality review process guarantees that your translations are accurate, polished, and ready for your global audience.',
                    'ctaText' => 'Learn More',
                    'ctaUrl' => '#',
                    'keyBenefits' => [
                        'Catch and correct linguistic errors',
                        'Enhance overall translation quality',
                        'Maintain consistency across all content',
                    ],
                ],
                [
                    'title' => 'Technical Support',
                    'description' => 'Get priority support for your Craft Translations installation.',
                    'message' => 'Maximize your efficiency with our dedicated technical support. Our team ensures your translation workflow runs smoothly, allowing you to focus on what matters most.',
                    'ctaText' => 'Request Support',
                    'ctaUrl' => '#',
                    'keyBenefits' => [
                        'Direct access to our plugin experts',
                        'Faster resolution of technical issues',
                        'Personalized guidance for optimal plugin use',
                    ],
                ],
                [
                    'title' => 'AI/Machine Translation',
                    'description' => 'Leverage cutting-edge AI for fast, cost-effective translations.',
                    'message' => 'Ideal for high-volume, time-sensitive projects. Our AI translation perfectly balances speed, cost, and quality for appropriate content types.',
                    'ctaText' => 'Coming Soon',
                    'ctaUrl' => '#',
                    'keyBenefits' => [
                        'High-speed translation for large volumes',
                        'Cost-effective solution for suitable content types',
                        'Continuous improvement with machine learning',
                    ],
                ],
            ];

        $variables['servicesData']  = $servicesData;            
        $this->requireLogin();
        return $this->renderTemplate('translations/services/_index', $variables);
    }
}
<?php
/**
 * SkillSync Sri Lanka Industry Sectors Master Registry
 * Comprehensive list of industry sectors available in Sri Lanka
 */

if (!function_exists('getSriLankaIndustries')) {
    function getSriLankaIndustries() {
        return [
            'Information Technology & Software (ICT)',
            'Banking, Financial Services & Insurance (BFSI)',
            'Telecommunications & Network Engineering',
            'Apparel, Textiles & Fashion Manufacturing',
            'Tourism, Hospitality & Travel Management',
            'Healthcare, Pharmaceuticals & Medical Sciences',
            'Engineering, Construction & Real Estate',
            'Supply Chain, Logistics, Maritime & Aviation',
            'Agriculture, Plantation, Tea & Food Processing',
            'Education, Academic & Corporate Training',
            'E-commerce, Retail & FMCG',
            'Media, Advertising, Digital Marketing & PR',
            'Renewable Energy, Power & Utilities',
            'Business Process Outsourcing (BPO / BPM / KPO)',
            'Automotive & Heavy Transport',
            'Legal, Consulting & Professional Auditing'
        ];
    }
}

if (!function_exists('renderIndustryOptions')) {
    function renderIndustryOptions($selectedValue = '', $placeholder = '-- Select Industry Sector --') {
        $industries = getSriLankaIndustries();
        $output = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        foreach ($industries as $ind) {
            $selected = (strcasecmp($selectedValue ?? '', $ind) === 0) ? ' selected' : '';
            $output .= '<option value="' . htmlspecialchars($ind) . '"' . $selected . '>' . htmlspecialchars($ind) . '</option>';
        }
        return $output;
    }
}


<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * The list of countries offered by the org_location multi-select on the AI
 * Context Questions tab, grouped by region.
 *
 * This is a static list: SimpleRisk used to refresh it from restcountries.com,
 * but that API was deprecated and its replacement is not licensed for
 * commercial use, so there is no live source any more. Update this list by
 * hand if a country is added, removed or renamed.
 *
 * Shape: [region => [country name, ...]], region keys sorted, country names
 * sorted (byte order, as sort() orders them) within each region.
 *
 * The names are exactly the strings the old live fetch produced (restcountries
 * `name.common`), including the territories. They must not be "corrected" to
 * another spelling: the multi-select stores the selected name itself, and
 * display_generic_multiselect() only re-selects a saved value that matches an
 * option exactly, so renaming an entry (e.g. "Cape Verde" to "Cabo Verde")
 * silently drops it from every instance that already saved it.
 */
function get_countries(): array
{
    return [
        'Africa' => [
            'Algeria', 'Angola', 'Benin', 'Botswana', 'British Indian Ocean Territory',
            'Burkina Faso', 'Burundi', 'Cameroon', 'Cape Verde', 'Central African Republic', 'Chad',
            'Comoros', 'DR Congo', 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Eswatini',
            'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Ivory Coast',
            'Kenya', 'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania',
            'Mauritius', 'Mayotte', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria',
            'Republic of the Congo', 'Rwanda', 'Réunion',
            'Saint Helena, Ascension and Tristan da Cunha', 'Senegal', 'Seychelles', 'Sierra Leone',
            'Somalia', 'South Africa', 'South Sudan', 'Sudan', 'São Tomé and Príncipe', 'Tanzania',
            'Togo', 'Tunisia', 'Uganda', 'Western Sahara', 'Zambia', 'Zimbabwe',
        ],
        'Americas' => [
            'Anguilla', 'Antigua and Barbuda', 'Argentina', 'Aruba', 'Bahamas', 'Barbados',
            'Belize', 'Bermuda', 'Bolivia', 'Brazil', 'British Virgin Islands', 'Canada',
            'Caribbean Netherlands', 'Cayman Islands', 'Chile', 'Colombia', 'Costa Rica', 'Cuba',
            'Curaçao', 'Dominica', 'Dominican Republic', 'Ecuador', 'El Salvador',
            'Falkland Islands', 'French Guiana', 'Greenland', 'Grenada', 'Guadeloupe', 'Guatemala',
            'Guyana', 'Haiti', 'Honduras', 'Jamaica', 'Martinique', 'Mexico', 'Montserrat',
            'Nicaragua', 'Panama', 'Paraguay', 'Peru', 'Puerto Rico', 'Saint Barthélemy',
            'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Martin', 'Saint Pierre and Miquelon',
            'Saint Vincent and the Grenadines', 'Sint Maarten', 'Suriname', 'Trinidad and Tobago',
            'Turks and Caicos Islands', 'United States', 'United States Minor Outlying Islands',
            'United States Virgin Islands', 'Uruguay', 'Venezuela',
        ],
        'Antarctic' => [
            'Antarctica', 'Bouvet Island', 'French Southern and Antarctic Lands',
            'Heard Island and McDonald Islands', 'South Georgia',
        ],
        'Asia' => [
            'Afghanistan', 'Armenia', 'Azerbaijan', 'Bahrain', 'Bangladesh', 'Bhutan', 'Brunei',
            'Cambodia', 'China', 'Georgia', 'Hong Kong', 'India', 'Indonesia', 'Iran', 'Iraq',
            'Israel', 'Japan', 'Jordan', 'Kazakhstan', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Lebanon',
            'Macau', 'Malaysia', 'Maldives', 'Mongolia', 'Myanmar', 'Nepal', 'North Korea', 'Oman',
            'Pakistan', 'Palestine', 'Philippines', 'Qatar', 'Saudi Arabia', 'Singapore',
            'South Korea', 'Sri Lanka', 'Syria', 'Taiwan', 'Tajikistan', 'Thailand', 'Timor-Leste',
            'Turkey', 'Turkmenistan', 'United Arab Emirates', 'Uzbekistan', 'Vietnam', 'Yemen',
        ],
        'Europe' => [
            'Albania', 'Andorra', 'Austria', 'Belarus', 'Belgium', 'Bosnia and Herzegovina',
            'Bulgaria', 'Croatia', 'Cyprus', 'Czechia', 'Denmark', 'Estonia', 'Faroe Islands',
            'Finland', 'France', 'Germany', 'Gibraltar', 'Greece', 'Guernsey', 'Hungary', 'Iceland',
            'Ireland', 'Isle of Man', 'Italy', 'Jersey', 'Kosovo', 'Latvia', 'Liechtenstein',
            'Lithuania', 'Luxembourg', 'Malta', 'Moldova', 'Monaco', 'Montenegro', 'Netherlands',
            'North Macedonia', 'Norway', 'Poland', 'Portugal', 'Romania', 'Russia', 'San Marino',
            'Serbia', 'Slovakia', 'Slovenia', 'Spain', 'Svalbard and Jan Mayen', 'Sweden',
            'Switzerland', 'Ukraine', 'United Kingdom', 'Vatican City', 'Åland Islands',
        ],
        'Oceania' => [
            'American Samoa', 'Australia', 'Christmas Island', 'Cocos (Keeling) Islands',
            'Cook Islands', 'Fiji', 'French Polynesia', 'Guam', 'Kiribati', 'Marshall Islands',
            'Micronesia', 'Nauru', 'New Caledonia', 'New Zealand', 'Niue', 'Norfolk Island',
            'Northern Mariana Islands', 'Palau', 'Papua New Guinea', 'Pitcairn Islands', 'Samoa',
            'Solomon Islands', 'Tokelau', 'Tonga', 'Tuvalu', 'Vanuatu', 'Wallis and Futuna',
        ],
    ];
}

<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Static fallback for fetchCountries() (includes/functions.php), used when
 * the live restcountries.com fetch fails or returns nothing to work with.
 *
 * restcountries.com's v3.1 endpoint (the one fetchCountriesFromAPI() calls)
 * now 301-redirects to a "legacy API deprecated" notice instead of country
 * data, and the v5 replacement requires an API key this Extra has no
 * configuration path for -- so a live fetch cannot currently succeed at
 * all, not just intermittently. Without this fallback the org_location
 * multi-select on the AI Context Questions tab renders with zero options.
 *
 * Same shape fetchCountriesFromAPI() returns: [region => [country name, ...]],
 * region keys sorted, country names sorted within each region. Common names,
 * not ISO official long-form names, to match what that function produces
 * from restcountries' `name.common` field. Deliberately not exhaustive
 * (micro-territories and dependencies are omitted) -- this is a safety net
 * for a country-picker field, not an authoritative ISO 3166 registry; the
 * live API remains the source of truth whenever it's reachable.
 */
function get_fallback_countries(): array
{
    return [
        'Africa' => [
            'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi',
            'Cabo Verde', 'Cameroon', 'Central African Republic', 'Chad', 'Comoros',
            'DR Congo', 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea',
            'Eswatini', 'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea',
            'Guinea-Bissau', 'Ivory Coast', 'Kenya', 'Lesotho', 'Liberia', 'Libya',
            'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco',
            'Mozambique', 'Namibia', 'Niger', 'Nigeria', 'Republic of the Congo',
            'Rwanda', 'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia',
            'South Africa', 'South Sudan', 'Sudan', 'São Tomé and Príncipe',
            'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe',
        ],
        'Americas' => [
            'Antigua and Barbuda', 'Argentina', 'Bahamas', 'Barbados', 'Belize',
            'Bolivia', 'Brazil', 'Canada', 'Chile', 'Colombia', 'Costa Rica',
            'Cuba', 'Dominica', 'Dominican Republic', 'Ecuador', 'El Salvador',
            'Grenada', 'Guatemala', 'Guyana', 'Haiti', 'Honduras', 'Jamaica',
            'Mexico', 'Nicaragua', 'Panama', 'Paraguay', 'Peru',
            'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines',
            'Suriname', 'Trinidad and Tobago', 'United States', 'Uruguay', 'Venezuela',
        ],
        'Antarctic' => [
            'Antarctica',
        ],
        'Asia' => [
            'Afghanistan', 'Armenia', 'Azerbaijan', 'Bahrain', 'Bangladesh', 'Bhutan',
            'Brunei', 'Cambodia', 'China', 'Cyprus', 'Georgia', 'India', 'Indonesia',
            'Iran', 'Iraq', 'Israel', 'Japan', 'Jordan', 'Kazakhstan', 'Kuwait',
            'Kyrgyzstan', 'Laos', 'Lebanon', 'Malaysia', 'Maldives', 'Mongolia',
            'Myanmar', 'Nepal', 'North Korea', 'Oman', 'Pakistan', 'Palestine',
            'Philippines', 'Qatar', 'Saudi Arabia', 'Singapore', 'South Korea',
            'Sri Lanka', 'Syria', 'Taiwan', 'Tajikistan', 'Thailand', 'Timor-Leste',
            'Turkey', 'Turkmenistan', 'United Arab Emirates', 'Uzbekistan',
            'Vietnam', 'Yemen',
        ],
        'Europe' => [
            'Albania', 'Andorra', 'Austria', 'Belarus', 'Belgium',
            'Bosnia and Herzegovina', 'Bulgaria', 'Croatia', 'Czechia', 'Denmark',
            'Estonia', 'Finland', 'France', 'Germany', 'Greece', 'Hungary',
            'Iceland', 'Ireland', 'Italy', 'Kosovo', 'Latvia', 'Liechtenstein',
            'Lithuania', 'Luxembourg', 'Malta', 'Moldova', 'Monaco', 'Montenegro',
            'Netherlands', 'North Macedonia', 'Norway', 'Poland', 'Portugal',
            'Romania', 'Russia', 'San Marino', 'Serbia', 'Slovakia', 'Slovenia',
            'Spain', 'Sweden', 'Switzerland', 'Ukraine', 'United Kingdom',
            'Vatican City',
        ],
        'Oceania' => [
            'Australia', 'Fiji', 'Kiribati', 'Marshall Islands', 'Micronesia',
            'Nauru', 'New Zealand', 'Palau', 'Papua New Guinea', 'Samoa',
            'Solomon Islands', 'Tonga', 'Tuvalu', 'Vanuatu',
        ],
    ];
}

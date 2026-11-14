<?php
// Single source of truth for the lists used by forms, filters and server validation.
// The pages pass these to the browser inside the #boot JSON block.

const CUSTOMER_STAGES = [
    'ENQUIRY' => 'New enquiry',
    'QUOTED' => 'Quote sent',
    'BOOKED' => 'Job booked',
    'COMPLETED' => 'Job completed',
    'LOST' => 'Not proceeding',
];

const JOB_STATUSES = [
    'QUOTED' => 'Quoted',
    'BOOKED' => 'Booked',
    'IN_PROGRESS' => 'In progress',
    'COMPLETED' => 'Completed',
];

const JOB_TYPES = ['Boundary', 'Boundary & gate', 'Garden', 'Garden & gate', 'Gate', 'Sliding Gate', 'Pergola', 'Various'];
const MATERIALS = ['Colorbond', 'Timber', 'Slat Aluminium', 'Bars Aluminium', 'Tubular', 'Polycarbonate sheets', 'Other/Various'];
const SLEEPER_OPTIONS = ['No Sleepers', 'Timber 20cm'];
const LEAD_SOURCES = ['Google', 'Referral', 'Repeat Customer', 'Saw a sign / saw my work', 'Other'];

const REPORT_CATEGORIES = [
    'JOBS' => 'Job profitability',
    'SOURCES' => 'Lead sources',
    'MATERIALS' => 'Materials',
    'TYPES' => 'Work types',
    'SUBURBS' => 'Suburbs',
    'CUSTOMERS' => 'Customers',
];

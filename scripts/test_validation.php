#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use Cartly\Validation\AuthValidator;
use Cartly\Validation\PlanValidator;
use Cartly\Services\RateLimiterService;
use Respect\Validation\Exceptions\ValidationException;

echo "=== Testing Validation and Rate Limiting ===\n\n";

// Test 1: AuthValidator - Valid login
echo "Test 1: Valid login credentials\n";
try {
    AuthValidator::validateLogin([
        'email' => 'user@example.com',
        'password' => 'SecurePass123'
    ]);
    echo "✓ PASS: Valid login credentials accepted\n\n";
} catch (ValidationException $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 2: AuthValidator - Invalid email
echo "Test 2: Invalid email format\n";
try {
    AuthValidator::validateLogin([
        'email' => 'not-an-email',
        'password' => 'SecurePass123'
    ]);
    echo "✗ FAIL: Should have rejected invalid email\n\n";
} catch (ValidationException $e) {
    echo "✓ PASS: Rejected invalid email - " . $e->getFullMessage() . "\n\n";
}

// Test 3: AuthValidator - Missing password
echo "Test 3: Missing password field\n";
try {
    AuthValidator::validateLogin([
        'email' => 'user@example.com'
    ]);
    echo "✗ FAIL: Should have rejected missing password\n\n";
} catch (ValidationException $e) {
    echo "✓ PASS: Rejected missing password - Key 'password' must be present\n\n";
}

// Test 4: PlanValidator - Valid plan creation
echo "Test 4: Valid plan creation\n";
try {
    PlanValidator::validateCreate([
        'name' => 'Basic Plan',
        'slug' => 'basic-plan',
        'price' => 29.99,
        'billing_cycle' => 'monthly'
    ]);
    echo "✓ PASS: Valid plan data accepted\n\n";
} catch (ValidationException $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 5: PlanValidator - Invalid billing cycle
echo "Test 5: Invalid billing cycle\n";
try {
    PlanValidator::validateCreate([
        'name' => 'Basic Plan',
        'slug' => 'basic-plan',
        'price' => 29.99,
        'billing_cycle' => 'weekly'  // Invalid cycle
    ]);
    echo "✗ FAIL: Should have rejected invalid billing_cycle\n\n";
} catch (ValidationException $e) {
    echo "✓ PASS: Rejected invalid billing_cycle\n\n";
}

// Test 6: PlanValidator - Invalid slug format
echo "Test 6: Invalid slug (contains uppercase)\n";
try {
    PlanValidator::validateCreate([
        'name' => 'Basic Plan',
        'slug' => 'BasicPlan',  // Invalid: uppercase
        'price' => 29.99,
        'billing_cycle' => 'monthly'
    ]);
    echo "✗ FAIL: Should have rejected uppercase in slug\n\n";
} catch (ValidationException $e) {
    echo "✓ PASS: Rejected uppercase in slug\n\n";
}

// Test 7: RateLimiterService - Basic rate limiter
echo "Test 7: Rate Limiter - Within limit\n";
try {
    $rateLimiter = new RateLimiterService();
    $limiter = $rateLimiter->getLimiter('login', 'user_123', ['limit' => 3, 'interval' => '1 minute']);
    
    $within1 = $rateLimiter->isWithinLimit($limiter);
    $within2 = $rateLimiter->isWithinLimit($limiter);
    $within3 = $rateLimiter->isWithinLimit($limiter);
    
    if ($within1 && $within2 && $within3) {
        echo "✓ PASS: First 3 requests allowed\n\n";
    } else {
        echo "✗ FAIL: Requests were rejected within limit\n\n";
    }
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 8: RateLimiterService - Exceeds limit
echo "Test 8: Rate Limiter - Exceeds limit\n";
try {
    $rateLimiter = new RateLimiterService();
    $limiter = $rateLimiter->getLimiter('login', 'user_456', ['limit' => 2, 'interval' => '1 minute']);
    
    $rateLimiter->isWithinLimit($limiter);  // 1st
    $rateLimiter->isWithinLimit($limiter);  // 2nd
    $within3 = $rateLimiter->isWithinLimit($limiter);  // 3rd should fail
    
    if (!$within3) {
        echo "✓ PASS: 3rd request exceeded limit and was rejected\n\n";
    } else {
        echo "✗ FAIL: 3rd request should have been rejected\n\n";
    }
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 9: PlanValidator - Price validation
echo "Test 9: Negative price rejection\n";
try {
    PlanValidator::validateCreate([
        'name' => 'Invalid Plan',
        'slug' => 'invalid-plan',
        'price' => -10,  // Invalid: negative
        'billing_cycle' => 'monthly'
    ]);
    echo "✗ FAIL: Should have rejected negative price\n\n";
} catch (ValidationException $e) {
    echo "✓ PASS: Rejected negative price\n\n";
}

// Test 10: AuthValidator - Short password
echo "Test 10: Password too short\n";
try {
    AuthValidator::validateLogin([
        'email' => 'user@example.com',
        'password' => 'short'  // Less than 6 chars
    ]);
    echo "✗ FAIL: Should have rejected short password\n\n";
} catch (ValidationException $e) {
    echo "✓ PASS: Rejected password shorter than 6 characters\n\n";
}

echo "=== Validation and Rate Limiting Tests Complete ===\n";

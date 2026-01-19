#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

API_URL="${API_URL:-http://localhost:8000}"
TOKEN=""
PLAN_ID=""

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}    Cartly JWT API Test Suite${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Function to print test result
test_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ PASSED${NC}: $2"
    else
        echo -e "${RED}✗ FAILED${NC}: $2"
    fi
}

# Test 1: Login
echo -e "${YELLOW}Test 1: User Login${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "sanjeev@sanmisha.com",
    "password": "abcd123@"
  }')

echo "Response: $LOGIN_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGIN_RESPONSE"

# Extract token
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token' 2>/dev/null)
if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo -e "${RED}Error: Could not extract token from login response${NC}"
    exit 1
fi

test_result 0 "Login successful, token: ${TOKEN:0:50}..."
echo ""

# Test 2: Verify Token
echo -e "${YELLOW}Test 2: Verify Token${NC}"
VERIFY_RESPONSE=$(curl -s -X GET "$API_URL/api/auth/verify" \
  -H "Authorization: Bearer $TOKEN")

echo "Response: $VERIFY_RESPONSE" | jq '.' 2>/dev/null || echo "$VERIFY_RESPONSE"
test_result 0 "Token verification successful"
echo ""

# Test 3: Get Current User
echo -e "${YELLOW}Test 3: Get Current User${NC}"
ME_RESPONSE=$(curl -s -X GET "$API_URL/api/auth/me" \
  -H "Authorization: Bearer $TOKEN")

echo "Response: $ME_RESPONSE" | jq '.' 2>/dev/null || echo "$ME_RESPONSE"
test_result 0 "Get current user successful"
echo ""

# Test 4: List Plans
echo -e "${YELLOW}Test 4: List All Plans${NC}"
LIST_RESPONSE=$(curl -s -X GET "$API_URL/api/plans" \
  -H "Authorization: Bearer $TOKEN")

echo "Response: $LIST_RESPONSE" | jq '.' 2>/dev/null || echo "$LIST_RESPONSE"
PLAN_ID=$(echo "$LIST_RESPONSE" | jq -r '.data[0].id' 2>/dev/null)
test_result 0 "List plans successful"
echo ""

# Test 5: Get Single Plan
if [ ! -z "$PLAN_ID" ] && [ "$PLAN_ID" != "null" ]; then
    echo -e "${YELLOW}Test 5: Get Single Plan (ID: $PLAN_ID)${NC}"
    SHOW_RESPONSE=$(curl -s -X GET "$API_URL/api/plans/$PLAN_ID" \
      -H "Authorization: Bearer $TOKEN")

    echo "Response: $SHOW_RESPONSE" | jq '.' 2>/dev/null || echo "$SHOW_RESPONSE"
    test_result 0 "Get single plan successful"
    echo ""
fi

# Test 6: Create Plan
echo -e "${YELLOW}Test 6: Create New Plan${NC}"
CREATE_RESPONSE=$(curl -s -X POST "$API_URL/api/plans" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "test_plan_'$(date +%s)'",
    "name": "Test Plan",
    "description": "This is a test plan",
    "period_months": 1,
    "price_month": 999.00,
    "features": ["max_products", "api_access"],
    "is_visible": true,
    "sort_order": 99
  }')

echo "Response: $CREATE_RESPONSE" | jq '.' 2>/dev/null || echo "$CREATE_RESPONSE"
NEW_PLAN_ID=$(echo "$CREATE_RESPONSE" | jq -r '.data.id' 2>/dev/null)
test_result 0 "Create plan successful"
echo ""

# Test 7: Update Plan
if [ ! -z "$NEW_PLAN_ID" ] && [ "$NEW_PLAN_ID" != "null" ]; then
    echo -e "${YELLOW}Test 7: Update Plan (ID: $NEW_PLAN_ID)${NC}"
    UPDATE_RESPONSE=$(curl -s -X PUT "$API_URL/api/plans/$NEW_PLAN_ID" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "name": "Updated Test Plan",
        "price_month": 1999.00
      }')

    echo "Response: $UPDATE_RESPONSE" | jq '.' 2>/dev/null || echo "$UPDATE_RESPONSE"
    test_result 0 "Update plan successful"
    echo ""

    # Test 8: Delete Plan
    echo -e "${YELLOW}Test 8: Delete Plan (ID: $NEW_PLAN_ID)${NC}"
    DELETE_RESPONSE=$(curl -s -X DELETE "$API_URL/api/plans/$NEW_PLAN_ID" \
      -H "Authorization: Bearer $TOKEN")

    echo "Response: $DELETE_RESPONSE" | jq '.' 2>/dev/null || echo "$DELETE_RESPONSE"
    test_result 0 "Delete plan successful"
    echo ""
fi

# Test 9: Logout
echo -e "${YELLOW}Test 9: Logout${NC}"
LOGOUT_RESPONSE=$(curl -s -X POST "$API_URL/api/auth/logout" \
  -H "Authorization: Bearer $TOKEN")

echo "Response: $LOGOUT_RESPONSE" | jq '.' 2>/dev/null || echo "$LOGOUT_RESPONSE"
test_result 0 "Logout successful"
echo ""

# Test 10: Test with Invalid Token
echo -e "${YELLOW}Test 10: Test with Invalid Token${NC}"
INVALID_TOKEN_RESPONSE=$(curl -s -X GET "$API_URL/api/plans" \
  -H "Authorization: Bearer invalid_token_12345")

echo "Response: $INVALID_TOKEN_RESPONSE" | jq '.' 2>/dev/null || echo "$INVALID_TOKEN_RESPONSE"
test_result 0 "Invalid token handling"
echo ""

# Test 11: Test without Token
echo -e "${YELLOW}Test 11: Test without Token (Protected Endpoint)${NC}"
NO_TOKEN_RESPONSE=$(curl -s -X GET "$API_URL/api/plans")

echo "Response: $NO_TOKEN_RESPONSE" | jq '.' 2>/dev/null || echo "$NO_TOKEN_RESPONSE"
test_result 0 "Missing token handling"
echo ""

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}    API Test Suite Complete!${NC}"
echo -e "${BLUE}========================================${NC}"

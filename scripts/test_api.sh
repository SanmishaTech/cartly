#!/bin/bash

# Cartly API Testing Script

BASE_URL="http://localhost:8000/api"

echo "=== Cartly JWT API Test ==="
echo ""

# 1. Test Login
echo "1. Testing Login..."
LOGIN_RESPONSE=$(curl -s -X POST $BASE_URL/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "sanjeev@sanmisha.com",
    "password": "abcd123@"
  }')

echo "Login Response:"
echo $LOGIN_RESPONSE | jq '.'
echo ""

# Extract token
TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token')
echo "Token: $TOKEN"
echo ""

# 2. Test Get Current User
if [ ! -z "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
  echo "2. Testing GET /api/auth/me..."
  curl -s -X GET $BASE_URL/auth/me \
    -H "Authorization: Bearer $TOKEN" | jq '.'
  echo ""

  # 3. Test List Plans
  echo "3. Testing GET /api/plans..."
  curl -s -X GET $BASE_URL/plans \
    -H "Authorization: Bearer $TOKEN" | jq '.'
  echo ""

  # 4. Test Create Plan
  echo "4. Testing POST /api/plans..."
  curl -s -X POST $BASE_URL/plans \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
      "code": "starter",
      "name": "Starter Plan",
      "description": "Perfect for beginners",
      "period_months": 1,
      "price_month": 999,
      "features": ["max_products", "api_access"],
      "is_visible": true,
      "sort_order": 0
    }' | jq '.'
  echo ""

else
  echo "Failed to get token!"
fi

echo "=== Test Complete ==="

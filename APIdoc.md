# u24594475 API Documentation

This document provides comprehensive documentation for the my COS216 API, detailing all available endpoints, required parameters, and example requests/responses.

## Table of Contents
1. [Authentication](#authentication)
2. [User Management](#user-management)
   - [Register](#register)
   - [Login](#login)
3. [Products](#products)
   - [Get All Products](#get-all-products)
4. [Preferences](#preferences)
5. [Wishlist](#wishlist)
6. [Cart](#cart)
7. [Orders](#orders)

---

## Authentication
All request types except `Register` and `Login` require an API key in the request body to prevent unauthorised use of the API. The API key of the user is returned during registration and login.

---

## User Management

### Register
Creates a new user account.

Use `type: "Register"` to register a new account with the provided information. 

Please remember validation should be done on the client-side too to prevent unnessacary requests to the API and server! Using client-side javascript validation gives the user immediate feedback on any input issues without requiring a round trip to the server. If for some reason client-side validation fails, the server also does validation. If validation fails server-side, an appropriate error message will be returned. If the user is registered successfully, their newly-generated API-key will be returned by the API.

- Passwords are not stored as plain text in the `users` database table. 
- Hashing Algorithm Used: `SHA-512` with a random 16-character salt generated for each user.
- `SHA-512` is built in to PHP without any additional libraries necessary.
- It provides sufficient hashing, especially since salt is added to passwords.
- `SHA-512` combined with a unique salt for each user offers sufficient resistance against rainbow table attacks and other common hash cracking techniques.

#### Parameters:
| Parameter | Required | Description | Validation |
|-----------|----------|-------------|------------|
| name | Yes | User's first name | 2-100 alphabetic chars |
| surname | Yes | User's last name | 2-100 alphabetic chars |
| email | Yes | User's email address | Valid email format |
| password | Yes | User's password | 9+ chars, upper/lower, digit, symbol |
| user_type | Yes | Account type | "Customer", "Courier" or "Inventory Manager" |

#### Example Request:
```json
{
  "type": "Register",
  "name": "John",
  "surname": "Doe",
  "email": "john.doe@example.com",
  "password": "SecurePass123!",
  "user_type": "Customer"
}
```

#### Example Response (Success):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": {
    "apikey": "abc123def456ghi789jkl012mno345pqr"
  },
  "code": 200
}
```

#### Example Response (Error):
```json
{
  "status": "error",
  "timestamp": 1625097600000,
  "data": "User already exists. The email address entered is already in the database.",
  "code": 409
}
```

---

### Login
Authenticates a user and returns their API key.

Use `type: "Login"` to attempt a login. If the user is found, their API key will be returned. If not, an error message will be returned.

#### Parameters:
| Parameter | Required | Description |
|-----------|----------|-------------|
| email | Yes | User's email address |
| password | Yes | User's password |

#### Example Request:
```json
{
  "type": "Login",
  "email": "john.doe@example.com",
  "password": "SecurePass123!"
}
```

#### Example Response (Success):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": [
    {
      "apikey": "abc123def456ghi789jkl012mno345pqr",
      "name": "John",
      "type": "Customer"
    }
  ],
  "code": 200
}
```

#### Example Response (Error):
```json
{
  "status": "error",
  "timestamp": 1625097600000,
  "data": "Email or password incorrect",
  "code": 401
}
```

---

## Products

### Get All Products
Retrieves a list of products with filtering, sorting, and field selection options.

Use `type: "GetAllProducts"` to retrieve products from the `products` table in the database. You can also use `GetAllProducts` to filter and/or sort by products in the database.

**Important:** Ensure that you **do not** use `fuzzy` search when searching for a specific product using the product's `id`. If you search for a product with `"id": 1` with `"fuzzy": true`, the API will return **ALL** products with the digit `1` in its `id` field.

#### Parameters:
| Parameter | Required | Description | Default |
|-----------|----------|-------------|---------|
| apikey | Yes | User's API key | - |
| type | Yes | Must be "GetAllProducts" | - |
| return | Yes | Fields to return (array or "*") | - |
| limit | No | Maximum number of results | 500 |
| sort | No | Field to sort by | - |
| order | No | Sort direction ("ASC" or "DESC") | "ASC" |
| fuzzy | No | Use fuzzy search (true/false) | true |
| search | No | Filter criteria (object) | - |

#### Available Search Fields:
`id`, `title`, `brand`, `categories`, `department`, `manufacturer`, `features`, `price_min`, `price_max`, `country_of_origin`

#### Example Request:
```json
{
  "type": "GetAllProducts",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "return": ["id", "title", "brand", "final_price"],
  "limit": 10,
  "sort": "final_price",
  "order": "ASC",
  "search": {
    "brand": "Nike",
    "price_min": 100,
    "price_max": 500
  }
}
```

#### Example Response (Success):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": [
    {
      "id": 123,
      "title": "Running Shoes",
      "brand": "Nike",
      "final_price": 129.99
    },
    {
      "id": 456,
      "title": "Athletic Shorts",
      "brand": "Nike",
      "final_price": 149.99
    }
  ],
  "code": 200
}
```

#### Example Response (Error):
```json
{
  "status": "error",
  "timestamp": 1625097600000,
  "data": "Invalid API Key (Unauthorised)",
  "code": 401
}
```

---

## Preferences
Manages user preferences including theme, currency, and default filters.

Use `type: "Preferences"` together with an `action` and valid `apikey` to interact with a user's preferences. To save a user's preferences, use `"action": "set"`. To retrieve a user's preferences, use `"action": "get"`.

#### Parameters:
| Parameter | Required | Description |
|-----------|----------|-------------|
| apikey | Yes | User's API key |
| action | Yes | "get" or "set" |
| preferences | For "set" | Preferences object |

#### Preference Fields:
- `theme`: "Light" or "Dark"
- `currency`: Currency code (e.g., "ZAR")
- `department_filter`: Default department filter
- `brand_filter`: Default brand filter
- `price_bracket_filter`: Default price range
- `country_filter`: Default country filter
- `sort_field`: Default sort field
- `sort_order`: Default sort order ("ASC" or "DESC")
- `last_search`: Last search query

#### Example Request (Get):
```json
{
  "type": "Preferences",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "get"
}
```

#### Example Response (Get):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": [
    {
      "theme": "Dark",
      "currency": "ZAR",
      "department_filter": "Electronics",
      "brand_filter": "Sony",
      "price_bracket_filter": "100-500",
      "country_filter": "South Africa",
      "sort_field": "final_price",
      "sort_order": "ASC",
      "last_search": "headphones"
    }
  ],
  "code": 200
}
```

#### Example Request (Set):
```json
{
  "type": "Preferences",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "set",
  "preferences": {
    "theme": "Dark",
    "currency": "USD",
    "department_filter": "Electronics"
  }
}
```

#### Example Response (Set):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": "Preferences saved successfully",
  "code": 200
}
```

---

## Wishlist
Manages a user's wishlist of products.

Use `type: "Wishlist"` along with an `action` and a valid `apikey`.
* To retrieve an array of `product_id`s in the user's wishlist, use `"action": "get"`.
* To add a product to a user's wishlist, use `"action": "add"`. If the product is already in the user's wishlist, the API will return `"status": "success"` but the message (`data`) will read `Product already in wishlist`. This is done to make client-side handling easier.
* To remove a product from a user's wishlist, use `"action": "remove"`. If the product is not in the user's wishlist, the API will return `"status": "success"` but the message (`data`) will read `Product not in wishlist`. This is done to make client-side handling easier. 

#### Parameters:
| Parameter | Required | Description |
|-----------|----------|-------------|
| apikey | Yes | User's API key |
| action | Yes | "add", "remove", or "get" |
| product_id | For "add"/"remove" | Product ID to modify |

#### Example Request (Add):
```json
{
  "type": "Wishlist",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "add",
  "product_id": 123
}
```

#### Example Response (Add):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": "Product added to wishlist",
  "code": 200
}
```

#### Example Request (Get):
```json
{
  "type": "Wishlist",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "get"
}
```

#### Example Response (Get):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": [123, 456, 789],
  "code": 200
}
```

---

## Cart
Manages a user's shopping cart.

Use `type: "Cart"` along with an `action` and a valid `apikey`.
* To retrieve an array of key-value pairs of `product_id`s and their respective quantities in the user's cart, use `"action": "get"`.
* To add a product to a user's cart, use `"action": "add"`. If the product is already in the user's cart, the product's quantity will be increased by 1.
* To update a product in the user's cart, use `"action": "update"` along with a `quantity`. `quantity` will be used to overwrite the current quantity in the user's cart. If the quantity is `0` (or less than 0), the product will be removed from the cart automatically.
* To remove a product from a user's cart, use `"action": "remove"`. If the product is not in the user's cart, the API will return `"status": "success"` but the message (`data`) will read `Product not in cart`. This is done to make client-side handling easier. 

#### Parameters:
| Parameter | Required | Description |
|-----------|----------|-------------|
| apikey | Yes | User's API key |
| action | Yes | "add", "remove", "update", "get", or "empty" |
| product_id | For "add"/"remove"/"update" | Product ID to modify |
| quantity | For "update" | New quantity |

#### Example Request (Add):
```json
{
  "type": "Cart",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "add",
  "product_id": 123
}
```

#### Example Response (Add):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": "Product added to cart",
  "code": 200
}
```

#### Example Request (Get):
```json
{
  "type": "Cart",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "get"
}
```

#### Example Response (Get):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": {
    "123": 2,
    "456": 1
  },
  "code": 200
}
```

---

## Orders
Handles order placement.

Use `type: "Order"` along with an `action`.
* To place an order, use `"action": "place"`. This will automatically add all of the products in the user's cart to the appropriate order, and clear the user's cart. The `order_id` will be returned if successful, otherwise an error message will be returned.
* To get the orders associated with a user (using their `apikey`), use `"action": "get"`. This will return an array of JSON objects where all of the data associated with each of the user's orders will be returned.

#### Parameters:
| Parameter | Required | Description |
|-----------|----------|-------------|
| apikey | Yes | User's API key |
| action | Yes | "place" or "get" |

#### Example Request (to place an order):
```json
{
  "type": "Order",
  "apikey": "abc123def456ghi789jkl012mno345pqr",
  "action": "place"
}
```

#### Example Response (Successfully placed an order):
```json
{
    "status": "success",
    "timestamp": 1745867078281,
    "data": {
        "order_id": 4
    },
    "code": 200
}
```

#### Example Response (Error when placing an order):
```json
{
  "status": "error",
  "timestamp": 1625097600000,
  "data": "Cannot place order with empty cart",
  "code": 400
}
```

#### Example Request (to get a user's orders):
```json
{
  "type": "Order",
  "action": "get",
  "apikey": "yby6Ulwp1BVdd3kVMp1TQzvNT7wC4lWa"
}
```

#### Example Response (to get a user's orders):
```json
{
    "status": "success",
    "timestamp": 1745867524748,
    "data": [
        {
            "order_id": 4,
            "state": "Storage",
            "delivery_date": "2025-05-25 13:30:05",
            "created_at": "2025-04-28 21:04:38",
            "products": [
                {
                    "product_id": 7,
                    "quantity": 1
                }
            ]
        },
        {
            "order_id": 3,
            "state": "Delivered",
            "delivery_date": "2025-05-21 11:17:29",
            "created_at": "2025-04-28 21:04:14",
            "products": [
                {
                    "product_id": 7,
                    "quantity": 1
                }
            ]
        },
        {
            "order_id": 2,
            "state": "Dispatched",
            "delivery_date": "2025-05-22 08:48:26",
            "created_at": "2025-04-28 20:21:55",
            "products": [
                {
                    "product_id": 7,
                    "quantity": 2
                }
            ]
        }
    ],
    "code": 200
}
```

#### Example Response (The user has no orders):
```json
{
    "status": "success",
    "timestamp": 1745867761303,
    "data": [],
    "code": 200
}
```

#### Example Response (Error, invalid api key):
```json
{
    "status": "error",
    "timestamp": 1745867799570,
    "data": "Invalid API key (Unauthorised)",
    "code": 401
}
```

---

## Error Handling
All errors follow the same format:
```json
{
  "status": "error",
  "timestamp": 1625097600000,
  "data": "Error message",
  "code": 400
}
```

Common error codes:
- `400`: Bad request (invalid parameters)
- `401`: Unauthorized (invalid API key)
- `404`: Not found (invalid product ID)
- `409`: Conflict (user already exists)
- `500`: Server error (when this happens, something is wrong on our side!)
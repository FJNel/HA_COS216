# u24594475 API Documentation

This document provides comprehensive documentation for the our COS216 API, detailing all available endpoints, required parameters, and example requests/responses.

## Table of Contents
1. [Authentication](#authentication)
2. [User Management](#user-management)
3. [Products](#products)
4. [Preferences](#preferences)
5. [Wishlist](#wishlist)
6. [Cart](#cart)
7. [Orders](#orders)
8. [Drones](#drones)
9. [Global Variables](#global-variables)

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
* To add a product to a user's cart, use `"action": "add"`. If the product is already in the user's cart, the product's quantity will be increased by 1. The user's cart cannot have more than 7 products.
* To update a product in the user's cart, use `"action": "update"` along with a `quantity`. `quantity` will be used to overwrite the current quantity in the user's cart. If the quantity is `0` (or less than 0), the product will be removed from the cart automatically. The user's cart cannot have more than 7 products. 
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

Handles order creation, updates, and retrieval for both customers and couriers.

### Order Actions:
- `create` or `place`: Creates a new order from the user's cart and clears their cart automatically. A user cannot place an order with more than 7 products.
    - **You can specify order fields** (`drone_id`, `latitude`, `longitude`, `state`) when creating an order. If not specified, defaults are used (`drone_id`, `latitude`, `longitude` are `null`, `state` is `"Storage"`).
- `update`: Updates order fields (like location, state, or drone assignment).
    - **A drone can only be assigned to an order if the order's delivery location is within 5km of HQ.** If not, the API returns an error.
- `get`: Retrieves orders based on user type (for a Customer, it retrieves all of this customer's orders; for a Courier, it retrieves all orders with state "Storage" by default, or as specified in the request).
    - **Returned order objects include the `drone_id` field.**

#### Parameters:
| Parameter | Required | Description | Valid Values |
|-----------|----------|-------------|--------------|
| apikey | Yes | User's API key | - |
| action | Yes | Action to perform | "create", "update", "get" |
| order_id | For update | ID of order to update | Existing order ID |
| latitude | For create/update | Delivery location latitude | -90.0 to 90.0 |
| longitude | For create/update | Delivery location longitude | -180.0 to 180.0 |
| drone_id | For create/update | Drone assigned to order | Existing drone ID or null |
| state | For create/update/get | Order state | "Storage", "Dispatched", "Delivered" |

#### Example Request (Create Order with custom fields):
```json
{
  "type": "Order",
  "apikey": "valid_customer_apikey",
  "action": "create",
  "latitude": -25.75,
  "longitude": 28.25,
  "state": "Storage"
}
```

#### Example Request (Update Order - assign drone):
```json
{
  "type": "Order",
  "apikey": "valid_courier_apikey",
  "action": "update",
  "order_id": 123,
  "drone_id": 1
}
```
> **Note:** If the order's delivery location is not within 5km of HQ, assigning a drone will fail with an error.

#### Example Response (Get Orders):
```json
{
  "status": "success",
  "timestamp": 1625097600000,
  "data": [
    {
      "order_id": 123,
      "customer_id": 5,
      "drone_id": 1,
      "state": "Dispatched",
      "delivery_date": "2025-05-20 14:00:00",
      "created_at": "2025-05-18 10:00:00",
      "latitude": -25.75,
      "longitude": 28.25,
      "products": [
        {"product_id": 10, "quantity": 2}
      ]
    }
  ],
  "code": 200
}
```

---

## Drones

Manages drone inventory and operations for Couriers.

### Drone Attributes:
- `drone_id`
- `current_operator_id`
- `is_available`
- `latest_latitude`
- `latest_longitude`
- `altitude`
- `battery_level`
- `state` (either `"Grounded at HQ"`, `"Flying"`, or `"Crashed"`)

### Drone Actions:
- `create`: Adds a new drone to the system. You can specify drone attributes when creating a drone. If you don't, default values will be used.
    - **By default, the drone's `latest_latitude` and `latest_longitude` are set to the HQ coordinates.**
    - **By default, the drone's `state` is `"Grounded at HQ"` and `is_available` is `true`.**
- `update`: Modifies drone properties as specified in the request object.
    - **If `state` is set to `"Grounded at HQ"`, `is_available` is automatically set to `true` and `current_operator_id` is cleared.**
    - **If `state` is set to `"Flying"`, `is_available` is set to `false` and `current_operator_id` is set to the courier performing the update.**
    - **If `state` is set to `"Crashed"`, `is_available` is set to `false` and `current_operator_id` remains unchanged.**
    - **You cannot update `current_operator_id` manually; the API manages this automatically.**
    - **When updating `latest_latitude` or `latest_longitude`, the new location must be within 5km of HQ. If not, the API returns an error.**
- `get`: Retrieves all drone information. If the user is a customer, only drones associated with their orders will be returned. If the user is a courier, all drones will be returned.
- `move` along with a `direction`: Couriers can move a drone by specifying a `drone_id` and a direction. The drone's new location must remain within 5km of HQ.
- `returnToHQ`: Returns the drone to the HQ coordinates, sets its `state` to `"Grounded at HQ"`, marks it as available, and clears `current_operator_id`.
- `dispatch` (Courier only): Assigns a drone to an order and sets both to "Dispatched"/"Flying". Only possible if the order is in "Storage" and has no drone assigned, and the drone is "Grounded at HQ" and available. The drone's `current_operator_id` is set to the courier. The order's delivery location must be within 5km of the HQ for this action to work.
- `deliver` (Courier only): Delivers the order if the drone is "Flying" and assigned to a "Dispatched" order. The drone must be within 10 meters of the order's delivery location. The order is marked as "Delivered", the drone is returned to HQ, and `current_operator_id` is cleared.
- `cancel` (Courier only): Cancels the delivery. The drone is returned to HQ, the order (if not delivered) is set back to "Storage" and unassigned, and `current_operator_id` is cleared.

#### Example Request (Create Drone):
```json
{
  "type": "Drone",
  "apikey": "valid_courier_apikey",
  "action": "create"
}
```
> **Note:** The drone will be created at the HQ coordinates and in the `"Grounded at HQ"` state by default.

#### Example Request (Move Drone):
```json
{
  "type": "Drone",
  "apikey": "valid_courier_apikey",
  "action": "move",
  "drone_id": 1,
  "direction": "up"
}
```
> **Note:** If the move would take the drone outside a 5km radius from HQ, the API will return an error.

#### Example Request (Return Drone to HQ):
```json
{
  "type": "Drone",
  "apikey": "valid_courier_apikey",
  "action": "returnToHQ",
  "drone_id": 1
}
```
> **Note:** This will set the drone's location to the HQ coordinates, state to `"Grounded at HQ"`, `is_available` to `true`, and clear the operator.

#### Example Request (Dispatch Drone to Order):
```json
{
  "type": "Drone",
  "apikey": "valid_courier_apikey",
  "action": "dispatch",
  "drone_id": 1,
  "order_id": 123
}
```
> **Note:** The drone must be available and at HQ, and the order must be in "Storage" with no drone assigned.

#### Example Request (Deliver Order):
```json
{
  "type": "Drone",
  "apikey": "valid_courier_apikey",
  "action": "deliver",
  "drone_id": 1
}
```
> **Note:** The drone must be "Flying", assigned to a "Dispatched" order, and within 10 meters of the delivery location.

#### Example Request (Cancel Delivery):
```json
{
  "type": "Drone",
  "apikey": "valid_courier_apikey",
  "action": "cancel",
  "drone_id": 1
}
```
> **Note:** The drone and order will be reset as if the delivery never happened (unless the order is already delivered).

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
- `403`: Forbidden (You are not allowed to do this action)
- `404`: Not found (invalid product ID)
- `409`: Conflict (user already exists)
- `422`: Unprocessable entity (Your request object cannot be processed for some reason)
- `500`: Server error (when this happens, something is wrong on our side!)

---

## Global Variables

**HQ Coordinates:**  
The API uses global variables for the HQ coordinates, which are used for all drone and order delivery logic.  
You can update these in the code by changing the following constants at the top of `api.php`:

```php
define('HQ_LATITUDE', -25.7472);
define('HQ_LONGITUDE', 28.2511);
```

All references to the HQ location in the API use these variables, making it easy to update the HQ location in one place.

---
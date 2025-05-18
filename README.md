# HA_COS216 - NodeJS Multi-User Socket Server

## Overview

This project implements the NodeJS multi-user socket server for the COS216 Homework Assignment courier system.  
It connects to the PHP API hosted on Wheatley and allows multiple clients (customers and couriers) to interact in real-time via WebSockets.

---

## How to Run

1. **Install dependencies:**
   ```sh
   npm install
   ```

2. **Set your API credentials:**
   - Edit `server.js` and update the `API_BASE_URL` with your Wheatley username and password.

3. **Start the server:**
   ```sh
   node server.js
   ```
   - Enter a port number between 1024 and 49151 when prompted.

4. **Connect clients:**
   - Use your Angular frontend or a WebSocket tester (e.g., [PieSocket Tester](https://www.piesocket.com/websocket-tester)) to connect to `ws://localhost:<your_port>`.

---

## Supported Commands

### **Admin Console Commands**
- `CURRENTLY DELIVERING`  
  Shows all orders currently out for delivery to all connected clients.
- `DRONE STATUS`  
  Shows the status of all drones to all connected clients.
- `KILL <username>`  
  Disconnects the specified user.
- `QUIT`  
  Broadcasts shutdown and closes all connections.

### **Client WebSocket Commands**

#### **Login**
```json
{ "type": "Login", "email": "user@example.com", "password": "password" }
```

#### **Order and Drone Actions**
- **Get currently delivering orders**
  ```json
  { "type": "CURRENTLY_DELIVERING" }
  ```
- **Get drone status**
  ```json
  { "type": "DRONE_STATUS" }
  ```
- **Update order status**
  ```json
  { "type": "UPDATE_ORDER", "order_id": 123, "state": "Out for delivery" }
  ```
- **Update drone status**
  ```json
  { "type": "UPDATE_DRONE", "drone_id": 1, "fields": { "battery_level": 80, "altitude": 20 } }
  ```
- **Move drone**
  ```json
  { "type": "MOVE_DRONE", "drone_id": 1, "latitude": -25.7475, "longitude": 28.2513 }
  ```
- **Mark order as delivered**
  ```json
  { "type": "MARK_DELIVERED", "order_id": 123 }
  ```

---

## Real-Time Notifications

- **ORDER_UPDATE**: Broadcast when an order status changes.
- **DRONE_UPDATE**: Broadcast when a drone is updated.
- **DRONE_MOVED**: Broadcast when a drone moves.
- **ORDER_DELIVERED**: Broadcast when an order is marked as delivered.
- **DELIVERY_POSTPONED**: Broadcast if a courier disconnects and orders are postponed.
- **KILL**: Sent to a user if forcibly disconnected.
- **QUIT**: Sent to all users when the server is shutting down.

---

## Lost Socket Handling

- If a **courier** disconnects while operating a drone:
  - All "Out for delivery" orders for that drone are reset to "Storage".
  - The drone is marked as unavailable and battery set to 0.
  - All affected customers are notified with a `DELIVERY_POSTPONED` message.

---

## Update Strategy

**We use the "update every time" strategy:**  
Whenever an order or drone is changed (by a client or the server), the server immediately calls the PHP API to update the database and broadcasts the change to relevant clients.  
**Reason:**  
- Ensures all clients always see the latest state.
- Reduces risk of data loss if the server crashes.
- Simpler to implement and debug than interval-based polling.

---

## Security

- **Do not commit or upload your Wheatley credentials.**
- Store credentials in environment variables or a config file for production use.

---

## Authors

- Ferdinand Johannes Nel (u24594475)
- Zoe Joubert (u05084360)

---
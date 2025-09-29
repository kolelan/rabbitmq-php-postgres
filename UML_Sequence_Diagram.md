# UML Sequence Diagram - RabbitMQ PHP PostgreSQL Project

## Process Interaction Flow

```mermaid
sequenceDiagram
    participant Client as HTTP Client
    participant API as PHP API Server
    participant RabbitMQ as RabbitMQ
    participant Worker as PHP Worker
    participant DB as PostgreSQL
    participant Console as PHP Console
    participant Loki as Loki
    participant Grafana as Grafana
    participant pgAdmin as pgAdmin

    Note over Client, pgAdmin: System Initialization
    Client->>API: GET /health
    API->>DB: Check connection
    DB-->>API: Connection OK
    API-->>Client: {"status": "healthy"}

    Note over Client, pgAdmin: User Creation via API
    Client->>API: POST /users {"name": "John Doe"}
    API->>API: Validate input
    API->>DB: logMessage(queue_name, data, "sent")
    DB-->>API: message_id: 123
    API->>RabbitMQ: publishMessage("data_queue", data)
    RabbitMQ-->>API: Message queued
    API-->>Client: {"status": "accepted", "message_id": 123}

    Note over Client, pgAdmin: Message Processing
    RabbitMQ->>Worker: Consume message from "data_queue"
    Worker->>DB: logMessage(queue_name, data, "processing")
    DB-->>Worker: message_id: 123
    Worker->>Worker: processMessage(data)
    Worker->>DB: insertUser("John Doe")
    DB-->>Worker: User created
    Worker->>DB: updateMessageStatus(123, "processed")
    DB-->>Worker: Status updated
    Worker->>RabbitMQ: ACK message
    RabbitMQ-->>Worker: Message acknowledged

    Note over Client, pgAdmin: Console Interaction
    Console->>RabbitMQ: Connect & declare queue
    Console->>DB: Connect to database
    Console->>Console: Read user input from STDIN
    Console->>DB: logMessage(queue_name, data, "sent")
    DB-->>Console: message_id: 124
    Console->>RabbitMQ: publishMessage("data_queue", data)
    RabbitMQ-->>Console: Message queued
    Console->>Console: Display confirmation

    Note over Client, pgAdmin: Logging & Monitoring
    API->>Loki: Send structured logs
    Worker->>Loki: Send structured logs
    Console->>Loki: Send structured logs
    Loki->>Grafana: Provide log data
    Client->>Grafana: View logs via web UI
    Client->>pgAdmin: Manage database via web UI
    pgAdmin->>DB: Execute SQL queries
    DB-->>pgAdmin: Return query results

    Note over Client, pgAdmin: Error Handling
    Worker->>Worker: Exception during processing
    Worker->>DB: updateMessageStatus(123, "error", error_message)
    DB-->>Worker: Error logged
    Worker->>RabbitMQ: NACK message (retry)
    RabbitMQ->>Worker: Redeliver message

    Note over Client, pgAdmin: Health Monitoring
    Client->>API: GET /health
    API->>DB: Check database connection
    API->>RabbitMQ: Check queue connection
    API-->>Client: {"status": "healthy", "timestamp": "2025-01-01T12:00:00Z"}
```

## Component Architecture

```mermaid
graph TB
    subgraph "Client Layer"
        HTTP[HTTP Client]
        WebUI[Web Browser]
    end

    subgraph "Application Layer"
        API[PHP API Server<br/>Port 8080]
        Console[PHP Console<br/>Interactive CLI]
        Worker[PHP Worker<br/>Background Process]
    end

    subgraph "Message Queue"
        RabbitMQ[RabbitMQ<br/>Port 5672/15672]
    end

    subgraph "Data Layer"
        DB[(PostgreSQL<br/>Port 5432)]
    end

    subgraph "Monitoring & Admin"
        Loki[Loki<br/>Port 3100]
        Grafana[Grafana<br/>Port 3000]
        pgAdmin[pgAdmin<br/>Port 5050]
    end

    HTTP --> API
    WebUI --> Grafana
    WebUI --> pgAdmin
    
    API --> RabbitMQ
    API --> DB
    Console --> RabbitMQ
    Console --> DB
    Worker --> RabbitMQ
    Worker --> DB
    
    RabbitMQ --> Worker
    
    API --> Loki
    Console --> Loki
    Worker --> Loki
    Loki --> Grafana
    
    pgAdmin --> DB
```

## Data Flow Diagram

```mermaid
flowchart LR
    subgraph "Input Sources"
        API_IN[API Requests]
        CONSOLE_IN[Console Input]
    end

    subgraph "Processing"
        QUEUE[Message Queue<br/>data_queue]
        WORKER[Message Worker]
    end

    subgraph "Storage"
        USERS[Users Table]
        MESSAGES[Messages Table]
        LOGS[Application Logs]
    end

    subgraph "Output"
        API_RESP[API Responses]
        CONSOLE_OUT[Console Output]
        MONITORING[Monitoring UI]
    end

    API_IN --> QUEUE
    CONSOLE_IN --> QUEUE
    QUEUE --> WORKER
    WORKER --> USERS
    WORKER --> MESSAGES
    WORKER --> LOGS
    API_IN --> MESSAGES
    CONSOLE_IN --> MESSAGES
    API_IN --> API_RESP
    CONSOLE_IN --> CONSOLE_OUT
    LOGS --> MONITORING
```

## Key Interactions Explained

### 1. **API Request Flow**
- Client sends HTTP POST to `/users`
- API validates input and logs message to database
- API publishes message to RabbitMQ queue
- API returns immediate response (202 Accepted)

### 2. **Message Processing Flow**
- Worker consumes messages from RabbitMQ
- Worker logs processing start to database
- Worker processes the message (creates user)
- Worker updates message status to "processed"
- Worker acknowledges message to RabbitMQ

### 3. **Console Interaction Flow**
- Console connects to RabbitMQ and database
- User inputs data via STDIN
- Console logs and publishes message
- Console displays confirmation

### 4. **Error Handling Flow**
- If processing fails, worker logs error to database
- Worker sends NACK to RabbitMQ for retry
- Message is redelivered for retry processing

### 5. **Monitoring Flow**
- All services send structured logs to Loki
- Grafana queries Loki for log visualization
- pgAdmin provides direct database management

## Technology Stack

- **PHP 8.2**: Application runtime
- **RabbitMQ**: Message queuing system
- **PostgreSQL**: Primary database
- **Loki**: Log aggregation
- **Grafana**: Log visualization
- **pgAdmin**: Database administration
- **Docker**: Containerization
- **Composer**: PHP dependency management

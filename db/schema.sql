CREATE TABLE events (
    event_id CHAR(36) PRIMARY KEY,
    user_id INT,
    event_platform VARCHAR(15) NOT NULL,
    event_domain VARCHAR(50) NOT NULL,
    event_source VARCHAR(50) NOT NULL,
    event_action VARCHAR(50) NOT NULL,
    event_location VARCHAR(50),
    event_payload JSON NOT NULL,
    event_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

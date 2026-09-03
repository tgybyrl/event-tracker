-- scratch, not seed data for the real schema

CREATE TABLE events (
    event_id CHAR(36) PRIMARY KEY,
    event_platform VARCHAR(50) NOT NULL,
    event_payload JSON NOT NULL
);

INSERT INTO events (event_id, event_platform, event_payload) VALUES ('123e21312','web', '{"action": "add_to_cart", "product_id": 123}');

INSERT INTO events (event_id, event_platform, event_payload) VALUES ('231e432112','mobile', '{"action": "add_to_cart", "product_id": 124}');



SELECT * FROM events;

DROP TABLE events;

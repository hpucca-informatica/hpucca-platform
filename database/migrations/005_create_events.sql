CREATE SEQUENCE events_code_seq;

CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE DEFAULT ('EVT' || LPAD(nextval('events_code_seq')::TEXT, 6, '0')),
    tenant_id BIGINT NOT NULL,
    integration_source_id BIGINT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    external_id VARCHAR(150) NOT NULL,
    payload JSONB NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    processed_at TIMESTAMPTZ NULL,
    failed_at TIMESTAMPTZ NULL,
    last_error TEXT NULL,
    occurred_at TIMESTAMPTZ NULL,
    received_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT events_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT events_integration_source_id_foreign
        FOREIGN KEY (integration_source_id) REFERENCES integration_sources(id),
    CONSTRAINT events_source_external_type_unique UNIQUE (integration_source_id, external_id, event_type),
    CONSTRAINT events_status_check CHECK (status IN ('pending', 'processing', 'processed', 'failed')),
    CONSTRAINT events_attempts_check CHECK (attempts >= 0)
);

CREATE INDEX events_status_available_at_index ON events (status, available_at);
CREATE INDEX events_tenant_id_index ON events (tenant_id);
CREATE INDEX events_event_type_index ON events (event_type);
CREATE INDEX events_received_at_index ON events (received_at);

CREATE FUNCTION prevent_events_code_update()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.code IS DISTINCT FROM OLD.code THEN
        RAISE EXCEPTION 'events.code is immutable';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER events_code_immutable
BEFORE UPDATE OF code ON events
FOR EACH ROW
EXECUTE FUNCTION prevent_events_code_update();

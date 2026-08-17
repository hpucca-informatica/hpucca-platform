CREATE SEQUENCE integration_destinations_code_seq;

CREATE TABLE integration_destinations (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE DEFAULT ('DST' || LPAD(nextval('integration_destinations_code_seq')::TEXT, 6, '0')),
    tenant_id BIGINT NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    type VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    config JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT integration_destinations_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT integration_destinations_tenant_slug_unique UNIQUE (tenant_id, slug),
    CONSTRAINT integration_destinations_type_check CHECK (type IN ('n8n')),
    CONSTRAINT integration_destinations_status_check CHECK (status IN ('active', 'inactive')),
    CONSTRAINT integration_destinations_slug_format_check CHECK (slug = LOWER(slug) AND slug ~ '^[a-z0-9-]+$')
);

CREATE INDEX integration_destinations_tenant_status_index ON integration_destinations (tenant_id, status);

ALTER TABLE integration_sources
    ADD COLUMN destination_id BIGINT NULL;

ALTER TABLE integration_sources
    ADD CONSTRAINT integration_sources_destination_id_foreign
        FOREIGN KEY (destination_id) REFERENCES integration_destinations(id);

CREATE INDEX integration_sources_destination_id_index ON integration_sources (destination_id);

CREATE FUNCTION prevent_integration_destinations_code_update()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.code IS DISTINCT FROM OLD.code THEN
        RAISE EXCEPTION 'integration_destinations.code is immutable';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER integration_destinations_code_immutable
BEFORE UPDATE OF code ON integration_destinations
FOR EACH ROW
EXECUTE FUNCTION prevent_integration_destinations_code_update();

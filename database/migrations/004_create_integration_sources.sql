CREATE SEQUENCE integration_sources_code_seq;

CREATE TABLE integration_sources (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE DEFAULT ('SRC' || LPAD(nextval('integration_sources_code_seq')::TEXT, 6, '0')),
    tenant_id BIGINT NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    api_key_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_used_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT integration_sources_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT integration_sources_tenant_slug_unique UNIQUE (tenant_id, slug),
    CONSTRAINT integration_sources_status_check CHECK (status IN ('active', 'inactive'))
);

CREATE FUNCTION prevent_integration_sources_code_update()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.code IS DISTINCT FROM OLD.code THEN
        RAISE EXCEPTION 'integration_sources.code is immutable';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER integration_sources_code_immutable
BEFORE UPDATE OF code ON integration_sources
FOR EACH ROW
EXECUTE FUNCTION prevent_integration_sources_code_update();

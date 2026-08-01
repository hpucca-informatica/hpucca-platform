CREATE SEQUENCE users_code_seq;

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE DEFAULT ('USR' || LPAD(nextval('users_code_seq')::TEXT, 6, '0')),
    tenant_id BIGINT NOT NULL,
    login VARCHAR(60) NOT NULL,
    email VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'user',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT users_tenant_id_foreign
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT users_tenant_login_unique UNIQUE (tenant_id, login),
    CONSTRAINT users_tenant_email_unique UNIQUE (tenant_id, email),
    CONSTRAINT users_type_check CHECK (type IN ('owner', 'admin', 'manager', 'user')),
    CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive'))
);

CREATE FUNCTION prevent_users_code_update()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.code IS DISTINCT FROM OLD.code THEN
        RAISE EXCEPTION 'users.code is immutable';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER users_code_immutable
BEFORE UPDATE OF code ON users
FOR EACH ROW
EXECUTE FUNCTION prevent_users_code_update();

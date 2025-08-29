--
-- PostgreSQL database cluster dump
--

-- Started on 2025-08-29 19:28:18

SET default_transaction_read_only = off;

SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;

--
-- Roles
--

CREATE ROLE invitado;
ALTER ROLE invitado WITH SUPERUSER INHERIT CREATEROLE CREATEDB LOGIN NOREPLICATION NOBYPASSRLS PASSWORD 'md527542c716db2c37f4eb71ce146158251';
CREATE ROLE postgres;
ALTER ROLE postgres WITH SUPERUSER INHERIT CREATEROLE CREATEDB LOGIN REPLICATION BYPASSRLS PASSWORD 'md536a26cf86c8592fea41e7c125f728995';

--
-- User Configurations
--






-- Completed on 2025-08-29 19:28:18

--
-- PostgreSQL database cluster dump complete
--


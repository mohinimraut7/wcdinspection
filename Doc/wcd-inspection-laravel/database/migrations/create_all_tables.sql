-- ============================================================
-- WCD Inspection System — MySQL Tables
-- Same structure as Node.js project
-- Run in: phpMyAdmin or MySQL Workbench
-- ============================================================

CREATE DATABASE IF NOT EXISTS wcd_inspection;
USE wcd_inspection;

-- 1. Super Admins
CREATE TABLE superadmins (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  fullname        VARCHAR(100) NOT NULL,
  username        VARCHAR(50)  NOT NULL UNIQUE,
  email           VARCHAR(100),
  mobile          VARCHAR(10)  NOT NULL UNIQUE,
  password        VARCHAR(255) NOT NULL,
  is_active       TINYINT(1)   DEFAULT 1,
  otp             VARCHAR(10)  DEFAULT NULL,
  otp_expires_at  DATETIME     DEFAULT NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. State Admins
CREATE TABLE stateadmins (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  fullname   VARCHAR(100) NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(100),
  mobile     VARCHAR(10)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  state      VARCHAR(100) NOT NULL,
  createdby  INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (createdby) REFERENCES superadmins(id) ON DELETE SET NULL
);

-- 3. District Admins (WCD District Officers)
CREATE TABLE districtadmins (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  fullname   VARCHAR(100) NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(100),
  mobile     VARCHAR(10)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  state      VARCHAR(100) NOT NULL,
  district   VARCHAR(100) NOT NULL,
  createdby  INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (createdby) REFERENCES superadmins(id) ON DELETE SET NULL
);

-- 4. Inspection Officers (Ward/Taluka level)
CREATE TABLE inspectionofficers (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  fullname   VARCHAR(100) NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(100),
  mobile     VARCHAR(10)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  state      VARCHAR(100) NOT NULL,
  district   VARCHAR(100) NOT NULL,
  taluka     VARCHAR(100),
  ward       VARCHAR(100),
  createdby  INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (createdby) REFERENCES districtadmins(id) ON DELETE SET NULL
);

-- 5. Organizations (Mobile App Users)
CREATE TABLE organizations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  orgtype          ENUM('private','government','semi-government') NOT NULL,
  orgsector        VARCHAR(100),
  orgname          VARCHAR(200) NOT NULL,
  orgaddress       TEXT,
  ruralurban       ENUM('rural','urban'),
  district         VARCHAR(100),
  taluka           VARCHAR(100),
  mahapalika       VARCHAR(100),
  ward             VARCHAR(100),
  pincode          VARCHAR(10),
  revenuedivision  VARCHAR(100),
  regnotype        ENUM('GST','PAN','TAN'),
  regnovalue       VARCHAR(50),
  concernname      VARCHAR(100),
  concernmobile    VARCHAR(10) UNIQUE,
  concernemail     VARCHAR(100),
  password         VARCHAR(255) NOT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Survey Questions (31 POSH questions)
CREATE TABLE surveyquestions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  srno         INT NOT NULL,
  part         VARCHAR(10),
  questiontext TEXT NOT NULL,
  questionmare TEXT
);

-- 7. Organization Survey Responses
CREATE TABLE orgsurveyresponses (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  orgid       INT NOT NULL,
  questionid  INT NOT NULL,
  answer      ENUM('yes','no') NOT NULL,
  submittedat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (orgid)      REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (questionid) REFERENCES surveyquestions(id)
);

-- 8. Survey Assignments (District Admin assigns org to Officer)
CREATE TABLE surveyassignments (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  orgid      INT NOT NULL,
  officerid  INT NOT NULL,
  assignedby INT NOT NULL,
  status     ENUM('assigned','inspected','deassigned') DEFAULT 'assigned',
  assignedat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (orgid)      REFERENCES organizations(id),
  FOREIGN KEY (officerid)  REFERENCES inspectionofficers(id),
  FOREIGN KEY (assignedby) REFERENCES districtadmins(id)
);

-- 9. Inspection Reports
CREATE TABLE inspectionreports (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  assignmentid        INT NOT NULL,
  orgid               INT NOT NULL,
  officerid           INT NOT NULL,
  casetype            ENUM('case1','case2','case3','case4') NOT NULL,
  status              ENUM('compiled','notcompiled','pending','rejected') NOT NULL,
  officername         VARCHAR(100),
  officerdesignation  VARCHAR(100),
  officersignature    TEXT,
  concernname         VARCHAR(100),
  concernsignature    TEXT,
  finalremark         TEXT,
  latitude            DECIMAL(10,8),
  longitude           DECIMAL(11,8),
  submittedat         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assignmentid) REFERENCES surveyassignments(id),
  FOREIGN KEY (orgid)        REFERENCES organizations(id),
  FOREIGN KEY (officerid)    REFERENCES inspectionofficers(id)
);

-- 10. Inspection Remarks (Case 2 — question-wise)
CREATE TABLE inspectionremarks (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  reportid    INT NOT NULL,
  questionid  INT NOT NULL,
  originalans ENUM('yes','no'),
  editedans   ENUM('yes','no'),
  remark      TEXT,
  FOREIGN KEY (reportid)   REFERENCES inspectionreports(id) ON DELETE CASCADE,
  FOREIGN KEY (questionid) REFERENCES surveyquestions(id)
);

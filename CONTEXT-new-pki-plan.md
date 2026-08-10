# **FOG Project PKI & Secure Boot Redesign: Implementation Plan**

## **1\. Core Architecture: The Multi-Tier Hierarchy**

The FOG PKI will transition to a strictly scoped, multi-tier Certificate Authority (CA) model. This eliminates the "Blast Radius" vulnerability of the legacy flat Root CA while maintaining zero-touch automation for administrators.

### **Architecture Diagram**

`graph TD`  
    `classDef offline fill:#f9d0c4,stroke:#e06666,stroke-width:2px;`  
    `classDef online fill:#d9ead3,stroke:#93c47d,stroke-width:2px;`  
    `classDef hybrid fill:#fff2cc,stroke:#ffd966,stroke-width:2px;`

    `Root["Tier 1: FOG Server Root CA<br/>(Pseudo-Offline | root:root 400)<br/>Public Key pinned via .msi"]:::offline`

    `Root --> SB_Int["Tier 2: Secure Boot Int. CA<br/>(Pseudo-Offline | root:root 400)<br/>Scope: codeSigning, Domain, IPs<br/>Public mok.der enrolled in UEFI"]:::offline`  
    `Root --> Web_Int["Tier 2: Web TLS Int. CA<br/>(Online on Master Server)<br/>Scope: serverAuth, RFC 1918"]:::online`  
    `Root --> Client_Leaf["Tier 2: Client Comm Leaf<br/>(Online on Master Server)<br/>Decrypts FOG Client AES Payloads"]:::online`

    `SB_Int --> Code_Leaf["Tier 3: Code Signing Leaves<br/>(Online on Master/Nodes)<br/>Signs bzImage FOS Kernels via sbsign"]:::online`  
    `Web_Int --> Web_Leaf["Tier 3: Web TLS Leaves<br/>(Online on Master/Nodes)<br/>Dynamic HTTPS for Master UI & Node APIs"]:::online`

### **Tier 1: Master Trust Anchor (FOG Server Root CA)**

> * **Role:** The ultimate trust anchor. The public key remains pinned to Windows endpoints (via the .msi installer) for FOG Client payload validation.  
> * **New Installs:** Will be generated with strict baseline constraints.  
> * **Key State:** Pseudo-Offlined (see Security Defaults).

### **Tier 2: Dedicated Intermediates & Client Leaf**

The Root CA directly issues three distinct certificates to isolate hardware trust, web infrastructure, and client communication:

> 1. **FOG Client Comm Leaf (Online)**  
   * **Role:** The private key (.srvprivate.key) remains online on the Master Server to decrypt AES payloads sent by the FOG Client. Because the client expects a leaf directly signed by the pinned Root, this bypasses the intermediate chain while keeping the Root safely separated from web vulnerabilities.  
> 2. **Secure Boot Intermediate CA (Pseudo-Offline)**  
   * **Constraints:** Strictly constrained to Secure Boot code signing, permitted internal IP ranges, and designated domains.  
   * **Role:** Issues short-lived Code Signing Leaf Certificates to the Master Server and any Storage Nodes to sign Linux FOS kernels (bzImage).  
   * **Portability:** The public key (mok.der) is fully portable and will automatically distribute and enroll to all nodes.  
> 3. **Web TLS Intermediate CA (Online)**  
   * **Constraints:** Name Constraints explicitly scoped to RFC 1918 Private IPs and local domain(s).  
   * **Role:** Remains online on the Master Server to dynamically issue Web TLS Leaf Certificates for the Master web UI and all distributed Storage Nodes.

## **2\. Security Defaults: "Pseudo-Offline" Kernel Isolation**

To protect environments that lack physical offline vaults, the installer will implement **Linux Kernel Isolation** by default.

> * **The Mechanism:** The private keys for the **Root CA** and the **Secure Boot Intermediate CA** will be strictly locked down at the OS level (chown root:root and chmod 400). This ensures that even if the FOG web application is compromised (e.g., via PHP Remote Code Execution), the www-data user cannot read or exfiltrate the master keys.  
> * **Admin Call-to-Action:** Both the CLI installer and the FOG Web UI will display a persistent banner/notice alerting the administrator that the keys are only pseudo-offline.  
> * **The Helper Workflow:** The UI/docs will direct admins to a helper command (or documented process) to fully extract and offline the Root and Secure Boot private keys to a secure vault.  
> * **Operational Caveat:** The documentation will explicitly note that the **Secure Boot CA private key** must be temporarily restored to the server to issue new kernel-signing leaf certificates whenever a new Storage Node is deployed or a new location is joined to the Master.

## **3\. Extensibility & Modularity (.fogsettings)**

The architecture is designed to be fully modular, supporting enterprise "Bring Your Own CA" (BYOCA) workflows natively.

> * **Config-Driven Paths:** All certificate and key locations will be parameterized as variables within .fogsettings.  
> * **Dynamic VHosts:** The Apache/Nginx vhost configurations will be written dynamically based on these settings.  
> * **Drop-In Overrides:** Administrators can seamlessly override the Web TLS paths with their own AD CS or ACME (Certbot) certificates. Because the architecture isolates Web TLS from Client Communication and Secure Boot, overriding the web certificates will not break the FOG Client or hardware imaging trust chains.

## **4\. Upgrade Path (Existing Installs)**

To ensure backwards compatibility and protect existing environments without orphaned endpoints:

> * **Retroactive Hardening:** The update script will apply the Linux Kernel Isolation (pseudo-offline permissions) to the existing FOG Server CA private key.  
> * **Seamless Transition:** The legacy Root will be repurposed to issue the new Intermediates and the dedicated Client Leaf. This preserves the existing ca.cert.der trust established on currently deployed Windows endpoints, requiring zero GPO updates or physical touches to existing machines while immediately securing the web and code-signing tiers moving forward.
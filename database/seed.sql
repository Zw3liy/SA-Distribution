-- Sample seed data for SA Business Distribution product catalog

INSERT INTO categories (name, slug, description) VALUES
('Business Laptops', 'business-laptops', 'Professional laptops built for business productivity and security.'),
('Servers & Storage', 'servers-storage', 'High-performance servers and storage systems for enterprise workloads.'),
('Networking', 'networking', 'Switches, routers, and wireless systems for resilient corporate networks.'),
('Cyber Security', 'cyber-security', 'Security appliances, access control, and CCTV solutions.');

INSERT INTO brands (name, slug, description) VALUES
('Dell Technologies', 'dell-technologies', 'Trusted enterprise computing and storage solutions.'),
('Hewlett Packard Enterprise', 'hewlett-packard-enterprise', 'Performance servers, networking, and hybrid cloud infrastructure.'),
('Cisco', 'cisco', 'Network infrastructure and cybersecurity products.'),
('Fortinet', 'fortinet', 'Integrated cybersecurity appliances and services.'),
('Lenovo', 'lenovo', 'Business laptops and workstations with enterprise-grade durability.'),
('APC by Schneider Electric', 'apc-by-schneider-electric', 'Power protection and UPS systems for business environments.');

INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, sale_price, stock, is_featured, is_new, is_on_sale) VALUES
('SAB-DL-4500', 'Dell Latitude 5500 Business Laptop', 'dell-latitude-5500', '14-inch business laptop with Intel Core i7 and enterprise security.', 'The Dell Latitude 5500 delivers reliable business performance, premium security features, and long battery life for modern corporate users.', 1, 1, 24999.00, NULL, 18, 1, 1, 0),
('SAB-LN-5600', 'Lenovo ThinkPad T14 Gen 3', 'lenovo-thinkpad-t14-gen-3', 'Business laptop engineered for durability and collaboration.', 'ThinkPad T14 Gen 3 combines powerful performance, advanced AI-enhanced conferencing, and modern security for mobile teams.', 1, 5, 23999.00, 21999.00, 12, 1, 0, 1),
('SAB-HP-2024', 'HPE ProLiant DL380 Gen11 Server', 'hpe-proliant-dl380-gen11', 'Industry-standard rack server for mission-critical applications.', 'HPE ProLiant DL380 Gen11 delivers scalable compute capacity, intelligent automation, and enterprise-class reliability.', 2, 2, 189999.00, NULL, 8, 1, 0, 0),
('SAB-HP-NV202', 'HPE Nimble Storage All-Flash Array', 'hpe-nimble-storage-all-flash', 'Ultra-fast all-flash storage with predictive analytics.', 'HPE Nimble Storage accelerates business applications and simplifies capacity planning with adaptive flash technology.', 2, 2, 379999.00, NULL, 5, 0, 0, 0),
('SAB-CS-9300', 'Cisco Catalyst 9300 Switch', 'cisco-catalyst-9300', 'Stackable enterprise switch for secure campus networking.', 'Cisco Catalyst 9300 delivers proven security features, high performance, and advanced management for enterprise campus networks.', 3, 3, 86999.00, 79999.00, 14, 0, 1, 1),
('SAB-CS-MR46', 'Cisco Meraki MR46 Access Point', 'cisco-meraki-mr46', 'Cloud-managed Wi-Fi 6 access point for business environments.', 'The Meraki MR46 provides secure, high-performance wireless connectivity with centralized cloud management and analytics.', 3, 3, 19999.00, NULL, 22, 0, 1, 0),
('SAB-FT-60F', 'Fortinet FortiGate 60F Firewall', 'fortinet-fortigate-60f', 'Next-gen firewall for small to medium enterprise offices.', 'FortiGate 60F offers advanced security, SD-WAN, and high-speed threat protection for distributed business networks.', 4, 4, 29999.00, 25999.00, 10, 1, 0, 1),
('SAB-APC-UPS2K', 'APC Smart-UPS 2000VA', 'apc-smart-ups-2000va', 'Reliable UPS power protection for business servers and networking equipment.', 'APC Smart-UPS 2000VA safeguards critical rack equipment from outages and voltage disturbances.', 4, 6, 25999.00, NULL, 20, 0, 0, 0);

INSERT INTO product_images (product_id, file_name, alt_text, is_primary, sort_order) VALUES
(1, 'dell-latitude-5500.webp', 'Dell Latitude 5500 business laptop', 1, 1),
(2, 'lenovo-thinkpad-t14.webp', 'Lenovo ThinkPad T14 Gen 3 laptop', 1, 1),
(3, 'hpe-proliant-dl380.webp', 'HPE ProLiant DL380 Gen11 server', 1, 1),
(4, 'hpe-nimble-storage.webp', 'HPE Nimble Storage all-flash array', 1, 1),
(5, 'cisco-catalyst-9300.webp', 'Cisco Catalyst 9300 switch', 1, 1),
(6, 'cisco-meraki-mr46.webp', 'Cisco Meraki MR46 access point', 1, 1),
(7, 'fortinet-fortigate-60f.webp', 'Fortinet FortiGate 60F firewall', 1, 1),
(8, 'apc-smart-ups-2000va.webp', 'APC Smart-UPS 2000VA UPS', 1, 1);

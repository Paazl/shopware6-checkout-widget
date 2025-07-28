# 6.0.12
- Add filters in order overview

# 6.0.11
- Regenerate product payload if missing on manual order export

# 6.0.10
- Add a choice between Stable and Latest Paazl api

# 6.0.9
- Fix weight on manual order export

# 6.0.8
- Only load js on checkout pages
- Fix loading of weight on Manual Paazl Export page
- Don't load Paazl API payload for empty cart

# 6.0.7
- Add Manual Paazl Export option

# 6.0.6
- Add Logging on OrderExportService exception

# 6.0.5
- Add config option for 'Widget check'
- Improve button handling in widget

# 6.0.4
- Fix for streets with numbers in street name

# 6.0.3
- Fix issue with Consignee address

# 6.0.2
- Implement latest changes of 6.5-version into 6.6 plugin

# 6.0.1
- Fix issue with Pickup Point house number

# 6.0.0
- Shopware 6.6 compatibility

# 1.5.1
- Reload checkout if Paazl widget failed to load
- Add logging after Paazl Widget Failed reload

# 1.5.0
- Improve reference table performance
- Add reference/token lifetime
- Fix parcel pickup address
- Fix province export

# 1.4.8
- Change priority for DeliveryProcessor to -5010
- Fix bug with new customer accounts

# 1.4.7
- Fix typo in update process

# 1.4.6
- Hide shipping costs if Paazl Session not active yet in cart
- Hide shipping costs if Paazl Session not active yet in checkout
- Auto reload aside summary when session initiated in checkout
- Fix cart shipping method name bug

# 1.4.5
- Fix bug in MailBeforeValidateSubscriber
- Add fallback to deprecated Shipping Method identification in Update flow

# 1.4.4
- Add console command `bin/console paazl:migrate-old` to migrate existing custom contexts to new Shipping Method entity

# 1.4.3
- Fix order export type bug

# 1.4.2
- Fix storefront bug

# 1.4.1
- Remove old compiled js from package

# 1.4.0
- Refactor
- Error hardening
- Allow decoration of API Client

# 1.3.10
- Fix : Code review feedback 

# 1.3.9
- Fix : change hardcoded paazl word to variable
- Fix : Handle if Paazl is down
- Fix : change logic for house number and house number extension for paazl payload

# 1.3.8
- Fix : The file DeliveryProcessor.php has multiple API calls to Paazl. Suggest moving this logic to a collector for better performance.
- Fix : account page breaks because of the twig blocks
- Fix : Paazl JS is loaded on checkout even if the shipping method is not selected for that sales channel

# 1.3.7
- Fix : widget refresh after change shipping method
- Fix : fixed address house number not added when add address line item 

# 1.3.6
- Improvements : sync override code with latest shopware version
- Improvements : remove unused code and files
- Improvements : Add subscriber with solid principle 
- Improvements : Add twig block for paazl code

# 1.3.5
- Fix : Widget not changing language based on locale

# 1.3.4
- Fix : Css not loaded issue on fallback

# 1.3.3
- Feature : Add locker tabs in available tabs

# 1.3.2
- Feature : Fallback widget if paazl is downage  

# 1.3.1
- Fix Swiss tax calculation issue
- Fallback if paazl do not get response 

# 1.3.0 
- version compatibility for shopware 6.5

# 1.2.4
- Delete paazl token from database
- Fixed : house number and house number extension are shown blank

# 1.2.3
- Move css field into widget configuration 
- Remove Paazl info in Email template and add paazl method
- Add configuration for use second address field as house number
- Data will be removed from database after uninstallation
- Register Cookies as technical required
- 500 error when using %paazlData%
- Max # options in Paazl config should be Int-fields
- 500 error when adding order lines
   
# 1.2.2
- Paazl extension address mapping
- Sent paid order to paazl
- Fixed shipping coast not changed issue

# 1.2.1
- Add price configuration

# 1.2.0
- fix billing address issue
- fix shipping address issue

# 1.1.2
- fix checkout result in wrong address data being used
- fix pickup tab it is no longer possible to change delivery address
- fix billing address in Shopware order is not stored issue

# 1.1.1
- fix validate API issue

# 1.1.0
- Update consignee address in payload
- Update consignee address  in email order confirmation shipping address to billing address

# 1.0.0
- initial version

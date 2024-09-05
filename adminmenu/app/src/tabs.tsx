import React from 'react';
import {CreateTabs, hasUrlParameter} from '@webstollen/react-jtl-plugin/lib'
import DashboardPage from "@webstollen/react-jtl-plugin/lib/components/DashboardPage";
import AdLink from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/AdLink";
import SetupAssistent from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/SetupAssistent";
import Support from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/Support";
import ShopInfo from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/ShopInfo";
import DashbarStatus from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/DashbarStatus";
import AdImage from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/AdImage";
import PageWrapper from "@webstollen/react-jtl-plugin/lib/components/PageWrapper";
import Card from "@webstollen/react-jtl-plugin/lib/components/Card";
import Orders from "./tabs/Orders";
import Queue from "./tabs/Queue";
import {DEBUG_URL_PARAM} from "@webstollen/react-jtl-plugin/lib/constants";
import TextInput from "@webstollen/react-jtl-plugin/lib/components/Settings/TextInput";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faCheckCircle} from "@fortawesome/pro-regular-svg-icons";
import Button from "@webstollen/react-jtl-plugin/lib/components/Button";
import CustomDashboardGridCards from "./tabs/Dashboard/CustomDashboardGridCards";
import PaymentMethods from './tabs/PaymentMethods';


/**
 * 'Dashboard' and 'Einstellungen' Tab will be added automatically if not specified here. If you want to customize the 'Dashboard' or 'Einstellungen' Tab,
 * just add them to the return array below as show with the 'Dashboard' tab.
 * The prop "settingsMap" and "pluginInfo" can be used to conditionally render tabs.
 */
const createTabs: CreateTabs = (settingsMap, pluginInfo) => {

    const setupAssistentSlides = [
        {
            id: 0, content:
                <div style={{padding: "1em"}}>
                    <h3 className={"mb-3 text-2xl font-family:comfortaa text-center"}>Einrichtungsassistent</h3>
                    <p>
                        Richte Mollie und das Shop Plugin in wenigen Schritten ein.
                    </p>
                </div>
        },
        {
            id: 1, content:
                <div style={{padding: "1em"}}>
                    <p>Hast Du noch keinen Account bei Mollie?</p>
                    <Button
                        onClick={() => (window.location.href = 'https://ws-url.de/mollie-pay')}
                        color="green"
                        className="mx-auto block my-6"
                    >
                        Jetzt kostenlos Mollie Account anlegen!
                    </Button>
                </div>
        },
        {
            id: 2, content:
                <div style={{padding: "1em"}}>
                    <h3 className={"mb-3 text-xl font-family:comfortaa text-center"}
                        style={{width: "100%"}}>API-Keys</h3>
                    <p>Die Keys findest du in <a href="https://ws-url.de/mollie-api-key-page" target={'_blank'}
                                                 rel="noreferrer" style={{textDecoration: "underline"}}>deinem Mollie
                        Konto.</a></p>
                    <div style={{display: "flex", alignItems: "center", width: 470}}><span
                        style={{flex: 2}}>Live Key:</span>
                        <div style={{flex: 7}}><TextInput settingId={'apiKey'}/></div>
                    </div>
                    <div style={{display: "flex", alignItems: "center", width: 470}}><span
                        style={{flex: 2}}>Test Key:</span>
                        <div style={{flex: 7}}><TextInput settingId={'test_apiKey'}/></div>
                    </div>
                    <div style={{display: "flex", alignItems: "center", width: 470}}><span
                        style={{flex: 2}}>Profil ID:</span>
                        <div style={{flex: 7}}><TextInput settingId={'profileId'}/></div>
                    </div>
                </div>
        },
        {
            id: 3, content:
                <div style={{padding: "1em"}}>
                    <p>Als nächsten Schritt müssen noch die</p>
                    <a href={pluginInfo.adminURL + '/' + (pluginInfo.shopVersionEqualOrGreaterThan520 ? 'shippingmethods' : 'versandarten.php')} target={'_blank'}
                       rel="noreferrer" style={{textDecoration: "underline"}}>Versandarten mit Zahlungsarten verknüpft werden.</a>
                </div>
        },
        {
            id: 4, content:
                <div style={{padding: "1em"}}>
                    <h3 className={"mb-3 text-xl font-family:comfortaa text-center"}>Fertig!</h3>
                    <FontAwesomeIcon color='green' icon={faCheckCircle} size='2x'/>
                    <p>Die grundlegende Einrichtung von Mollie Plugin ist fertig.</p>
                    <p>Bitte prüfe noch im Tab "Zahlungsarten", ob du noch etwas an bestimmten Zahlungsarten anpassen willst.</p>
                </div>
        }
    ]

    return [
        {
            title: 'Dashboard',
            isDashboard: true,
            component:
                <DashboardPage>
                    <AdLink/>
                    <SetupAssistent slides={setupAssistentSlides}/>
                    <Support/>
                    <CustomDashboardGridCards/>
                    <ShopInfo/>
                    <AdImage/>
                    <DashbarStatus/>
                </DashboardPage>
        },
        {
            title: 'Zahlungsarten',
            isDashboard: false,
            component:
                <PageWrapper>
                    <PaymentMethods/>
                </PageWrapper>
        },
        {
            title: 'Bestellungen',
            isDashboard: false,
            component:
                <PageWrapper thin>
                    <Card>
                        <Orders/>
                    </Card>
                </PageWrapper>
        },
        ...(hasUrlParameter(DEBUG_URL_PARAM)
            ? [
                {
                    title: 'Warteschlange',
                    isDashboard: false,
                    component:
                        <PageWrapper thin>
                            <Card>
                                <Queue/>
                            </Card>
                        </PageWrapper>
                },
            ]
            : []),
    ]
}

export default createTabs


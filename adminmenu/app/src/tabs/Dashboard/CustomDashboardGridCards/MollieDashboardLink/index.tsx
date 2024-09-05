import React from "react";
import Button from "@webstollen/react-jtl-plugin/lib/components/Button";
import DashboardGridCard from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/DashboardGridCard";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faExternalLink} from "@fortawesome/pro-regular-svg-icons";
import {Loading} from "@webstollen/react-jtl-plugin/lib";
import {usePluginSettings} from "@webstollen/react-jtl-plugin/lib/context/SettingsContext";

const MollieDashboardLink = () => {
    const {settingsMap } = usePluginSettings()

    return (
            <DashboardGridCard>
                <div className="w-full h-full relative">{/* This div only exists to prevent shadow from affecting other DashboardGridCards due to position:relative */}
                    <Loading loading={Object.keys(settingsMap ?? {}).length === 0} className={"h-full"}>
                        <div style={{flexDirection: "column", justifyContent: "center", gap: 20, height: "100%"}}
                             className="flex gap-1">
                            {settingsMap?.apiKey?.value ? (
                                <>
                                    <div className="flex items-center my-3">
                                        <img
                                            src="https://cdn.webstollen.de/plugins/ws_mollie_ws.svg"
                                            alt="Plugin Icon"
                                            className="mr-2 max-w-full"
                                            style={{maxWidth: '100px'}}
                                        />
                                        <div className="text-xl">
                                            Integriere alle wichtigen
                                            <br/>
                                            Zahlungsarten in kürzester Zeit.
                                        </div>
                                    </div>
                                    <div>
                                        <Button
                                            onClick={() => window.open('https://mollie.com/dashboard', '_blank')?.focus()}
                                            className={'mx-8'}>
                                            Mollie Dashboard <FontAwesomeIcon icon={faExternalLink}/>
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="flex items-center my-3">
                                        <img
                                            src="https://cdn.webstollen.de/plugins/ws_mollie_ws.svg"
                                            alt="Plugin Icon"
                                            className="mr-2 max-w-full"
                                            style={{maxWidth: '100px'}}
                                        />
                                        <div className="text-xl">
                                            Integriere alle wichtigen
                                            <br/>
                                            Zahlungsarten in kürzester Zeit.
                                        </div>
                                    </div>
                                    <Button
                                        onClick={() => (window.location.href = 'https://ws-url.de/mollie-pay')}
                                        color="green"
                                        className="mx-auto block my-6"
                                    >
                                        Jetzt kostenlos Mollie Account anlegen!
                                    </Button>
                                </>

                            )}
                        </div>
                    </Loading>
                </div>
        </DashboardGridCard>
    )
}

export default MollieDashboardLink;



function MktApp() {
  const [route, setRoute] = React.useState("home");
  React.useLayoutEffect(() => { window.lucide && window.lucide.createIcons(); });
  const go = (r) => { setRoute(r); window.scrollTo({ top: 0 }); };
  let view;
  if (route === "products") view = <window.MktProducts go={go} />;
  else if (route === "contact") view = <window.MktContact go={go} />;
  else view = <window.MktHome go={go} />;
  return (
    <React.Fragment>
      <window.MktNav go={go} route={route} />
      {view}
      <window.MktFooter go={go} />
    </React.Fragment>
  );
}
ReactDOM.createRoot(document.getElementById("root")).render(<MktApp />);

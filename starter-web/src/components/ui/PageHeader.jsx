export default function PageHeader({ title, lead, children }) {
  return (
    <div className="dash-page__intro">
      <h1 className="dash-page__title">{title}</h1>
      {lead && <p className="dash-page__lead">{lead}</p>}
      {children}
    </div>
  )
}


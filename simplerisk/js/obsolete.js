function showObsolete()
{
  elements = document.getElementsByClassName("obsolete");

  for(i=0; i<elements.length; i++)
  {
    elements[i].style.display = "";
  }

  document.getElementById("obsolete_menu").style.display = "none";
}
